<?php

namespace Pada\ResponseCacheBundle\Cache;

use Pada\ResponseCacheBundle\Service\CacheableServiceInterface;
use Pada\ResponseCacheBundle\Service\EvictServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;


class CacheWarmer implements CacheWarmerInterface
{
    private CacheableServiceInterface $cacheableService;
    private EvictServiceInterface $evictService;
    private LoggerInterface $logger;
    private string $scanDir;

    public function __construct(string                    $scanDir,
                                CacheableServiceInterface $service,
                                EvictServiceInterface     $evictService)
    {
        $this->scanDir = $scanDir;
        $this->logger = new NullLogger();
        $this->cacheableService = $service;
        $this->evictService = $evictService;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function isOptional()
    {
        return true;
    }

    /** @phpstan-ignore-next-line
     * @throws \Exception
     */
    public function warmUp($cacheDir)
    {
        try {
            $this->cacheableService->warmUpSystemCache($this->scanDir);
            $this->evictService->warmUpSystemCache($this->scanDir);
        } catch (\Exception $exception) {
            $this->logger->error('ResponseCacheBundle: could not warm cache. ' . $exception->getMessage());
        }
        return [];
    }
}
