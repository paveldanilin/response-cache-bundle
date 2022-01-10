<?php

namespace Pada\ResponseCacheBundle\Controller\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Annotation\Target({"METHOD"})
 */
class Cacheable extends AbstractAnnotation
{
    public const REQUEST_ATTRIBUTE = 'response_cache_bundle.annotation.cacheable';
    public const REQUEST_POOL_SERVICE = 'response_cache_bundle.attribute.cache.pool';
    public const REQUEST_CACHED_ITEM = 'response_cache_bundle.attribute.cached.item';
    public const REQUEST_LOCK = 'response_cache_bundle.attribute.lock';

    public ?int $ttl;
    public ?string $condition;

    public function __construct(array $data)
    {
        $this->extractPool($data);
        $this->extractKey($data);
        $this->ttl = $this->extractTtl($data);
        $this->condition = $this->extractCondition($data);
    }

    private function extractTtl(array $data): ?int
    {
        $ttl = $data['ttl'] ?? null;
        if (null === $ttl || $ttl < 0 || $ttl > PHP_INT_MAX) {
            return null;
        }
        return $ttl;
    }

    private function extractCondition(array $data): ?string
    {
        $condition = \trim($data['condition'] ?? '');
        if (empty($condition)) {
            return null;
        }
        return $condition;
    }
}
