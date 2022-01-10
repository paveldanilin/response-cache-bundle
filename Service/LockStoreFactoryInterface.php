<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\Lock\PersistingStoreInterface;

interface LockStoreFactoryInterface
{
    public function create(string $dsn): PersistingStoreInterface;
}
