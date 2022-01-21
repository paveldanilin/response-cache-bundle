<?php

namespace Pada\ResponseCacheBundle\Controller\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Annotation\Target({"METHOD"})
 */
class CacheEvict extends AbstractAnnotation
{
    public const REQUEST_ATTRIBUTE = 'response_cache_bundle.annotation.cache_evict';

    public function __construct(array $data)
    {
        $this->extractPool($data);
        $this->extractKey($data);
        $this->extractSkipKeyGen($data);
    }
}
