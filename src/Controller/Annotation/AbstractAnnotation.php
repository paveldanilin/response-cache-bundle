<?php

namespace Pada\ResponseCacheBundle\Controller\Annotation;

abstract class AbstractAnnotation
{
    public const DEFAULT_APP_CACHE_POOL = 'cache.app';

    public string $pool = self::DEFAULT_APP_CACHE_POOL;
    public string $key = '';

    protected function extractPool(array $data): void
    {
        $this->pool = $data['value'] ?? $data['pool'] ?? self::DEFAULT_APP_CACHE_POOL;
        if (empty($this->pool)) {
            throw new \InvalidArgumentException('A cache pool must be defined');
        }
    }

    /**
     *
     * @see https://symfony.com/doc/current/components/expression_language.html#expression-syntax
     * @param array $data
     * @return void
     */
    protected function extractKey(array $data): void
    {
        $this->key = \trim($data['key'] ?? '');
    }
}
