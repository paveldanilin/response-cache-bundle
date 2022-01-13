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
            'params' => $request->attributes->all()['_route_params'] ?? [],
            'query' => $request->query->all(),
            'request' => $request
        ];
    }

    private function getOrCreateExpressionLanguage(): ExpressionLanguage
    {
        if (null === $this->expressionLanguage) {
            $this->expressionLanguage = new ExpressionLanguage($this->cacheSystem);
            $this->expressionLanguage->register('knvl', fn($s) => $s, function ($arguments, $table, $key, $def = '') {
                return $table[$key] ?? $def;
            });
        }
        return $this->expressionLanguage;
    }
}
