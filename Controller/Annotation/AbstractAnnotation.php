<?php

namespace Pada\ResponseCacheBundle\Controller\Annotation;

abstract class AbstractAnnotation
{
    public const DEFAULT_KEY_HASH_FUNC = 'md5';
    public const DEFAULT_APP_CACHE_POOL = 'cache.app';

    /**
     * A Symfony pool name.
     * @var string
     */
    public string $pool = self::DEFAULT_APP_CACHE_POOL;
    /**
     * A key of the cached item.
     * Can be static or dynamic.
     * @var string
     */
    public string $key = '';
    /**
     * Default value: 'md5'
     * @var string|callable
     */
    public $keyHashFunc = self::DEFAULT_KEY_HASH_FUNC;


    protected function extractPool(array $data): void
    {
        $this->pool = $data['value'] ?? $data['pool'] ?? self::DEFAULT_APP_CACHE_POOL;
        if (empty($this->pool)) {
            throw new \InvalidArgumentException('A cache pool must be defined');
        }
    }

    /**
     * 'my_key' - static key
     * '#route_val('id')' - dynamic key
     * @see https://symfony.com/doc/current/components/expression_language.html#expression-syntax
     * @param array $data
     * @return void
     */
    protected function extractKey(array $data): void
    {
        $this->key = \trim($data['key'] ?? '');
    }

    protected function extractKeyHashFunc(array $data): void
    {
        if (\array_key_exists('keyHashFunc', $data)) {
            $this->keyHashFunc = $data['keyHashFunc'];
        } else {
            $this->keyHashFunc = self::DEFAULT_KEY_HASH_FUNC;
        }
    }
}
