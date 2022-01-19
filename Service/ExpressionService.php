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
            'route_params'      => $request->attributes->all()['_route_params'] ?? [],
            'query_params'      => $request->query->all(),
            'request'           => $request,
            'request_method'    => $request->getMethod(),
            'request_uri'       => $request->getRequestUri(),
            'request_locale'    => $request->getLocale(),
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
            return ExpressionService::extractArgumentValue($arguments, 'query_params', $key, $def);
        });
        // route_val(key, def = '')
        $expressionLanguage->register('route_val', fn($s) => $s, function ($arguments, $key, $def = '') {
            return ExpressionService::extractArgumentValue($arguments, 'route_params', $key, $def);
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
}
