<?php

namespace Pada\ResponseCacheBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;

final class ExpressionService implements ExpressionServiceInterface
{
    private CacheItemPoolInterface $cacheSystem;
    private ?ExpressionLanguage $expressionLanguage = null;

    public function __construct(CacheItemPoolInterface $cacheSystem)
    {
        $this->cacheSystem = $cacheSystem;
    }

    /**
     * @param string $expression
     * @param array $context
     * @return mixed
     */
    public function evaluate(string $expression, array $context = [])
    {
        return $this->getOrCreateExpressionLanguage()->evaluate($expression, $context);
    }

    /**
     * @param string $expression
     * @param Request $request
     * @return mixed
     */
    public function evaluateOnRequest(string $expression, Request $request)
    {
        return $this->evaluate($expression, $this->getRequestContext($request));
    }

    private function getRequestContext(Request $request): array
    {
        return [
            'request'           => $request,
            'request_method'    => $request->getMethod(),
            'request_uri'       => $request->getRequestUri(),
            'request_locale'    => $request->getLocale(),
            '_route_params'      => $request->attributes->all()['_route_params'] ?? [],
            '_query_params'      => $request->query->all(),
            '_request_content'  => fn() => $request->getContent(),
        ];
    }

    private function getOrCreateExpressionLanguage(): ExpressionLanguage
    {
        if (null === $this->expressionLanguage) {
            $this->expressionLanguage = $this->createExpressionLanguage();
        }
        return $this->expressionLanguage;
    }

    private function createExpressionLanguage(): ExpressionLanguage
    {
        $expressionLanguage = new ExpressionLanguage($this->cacheSystem);
        // query_val(key, def = '')
        $expressionLanguage->register('query_val', fn($s) => $s, function ($arguments, $key, $def = '') {
            return ExpressionService::extractArgumentValue($arguments, '_query_params', $key, $def);
        });
        // route_val(key, def = '')
        $expressionLanguage->register('route_val', fn($s) => $s, function ($arguments, $key, $def = '') {
            return ExpressionService::extractArgumentValue($arguments, '_route_params', $key, $def);
        });
        // body()
        $expressionLanguage->register('body', fn($s) => $s, function ($arguments) {
            $requestContent = $arguments['_request_content'] ?? '';
            if (empty($requestContent)) {
                return '';
            }
            return $requestContent();
        });
        // body_json(path)
        $expressionLanguage->register('body_json', fn($s) => $s, function ($arguments, $path, $def = '') {
            /** @var Request $req */
            $req = $arguments['request'];
            $reqAttrId = '__pada_resp_cache_parsed_body';

            if (!$req->attributes->has($reqAttrId)) {
                $requestContent = $arguments['_request_content'] ?? '';
                if (empty($requestContent)) {
                    return '';
                }
                $content = $requestContent();
                if (empty($content)) {
                    return $def;
                }
                $decodedBody = \json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                $req->attributes->set($reqAttrId, $decodedBody);
            } else {
                $decodedBody = $req->attributes->get($reqAttrId);
            }

            return self::findPath($path, $decodedBody, $def);
        });
        return $expressionLanguage;
    }

    /**
     * @param array $arguments
     * @param string $argKey
     * @param string|array $key
     * @param string $def
     * @return string
     */
    private static function extractArgumentValue(array $arguments, string $argKey, $key, string $def = ''): string
    {
        $ret = $def;
        $arg = $arguments[$argKey] ?? [];
        if (empty($arg)) {
            return $ret;
        }
        if (\is_string($key)) {
            $ret = $arg[$key] ?? $def;
        } elseif (\is_array($key)) {
            $values = \array_values(\array_intersect_key($arg, \array_flip($key)));
            if (!empty($values)) {
                $ret = \implode('', $values);
            }
        }
        return $ret;
    }

    /**
     * @param string $path
     * @param array $data
     * @param mixed $def
     * @return mixed
     */
    private static function findPath(string $path, array $data, $def)
    {
        $temp = &$data;
        $pathChunks = \explode('.', $path);
        $pathLen = \count($pathChunks);
        $pos = 0;

        foreach ($pathChunks as $key) {
            if (\is_array($temp)) {
                if (! \array_key_exists($key, $temp)) {
                    return $def;
                }
                $temp = &$temp[$key];
                $pos++;
            } elseif (\is_object($temp)) {
                if (! isset($temp->{$key})) {
                    return $def;
                }
                $temp = &$temp->{$key};
                $pos++;
            }
        }

        if ($pathLen != $pos) {
            return $def;
        }

        return $temp;
    }
}
