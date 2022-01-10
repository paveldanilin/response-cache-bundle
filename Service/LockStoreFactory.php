<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Lock\Store\RedisStore;

final class LockStoreFactory implements LockStoreFactoryInterface
{
    private const TYPE_FLOCK = 'flock';
    private const TYPE_MEMCACHED = 'memcached';
    private const TYPE_REDIS = 'redis';
    private const TYPE_PDO = 'pdo';

    public function create(string $dsn): PersistingStoreInterface
    {
        $parsed = $this->parse($dsn);
        return $this->createStoreAdapter($parsed['type'], $parsed['options']);
    }

    private function createStoreAdapter(string $type, array $options): PersistingStoreInterface
    {
        switch ($type) {
            // flock;lock_path=/tmp/locks
            case self::TYPE_FLOCK:
                return $this->createFlock($options['lock_path'] ?? null);
            // memcached;host=localhost;port=11211
            case self::TYPE_MEMCACHED:
                return $this->createMemcached($options['host'] ?? 'localhost', $options['port'] ?? '11211');
            // redis;host=localhost;port=6379
            case self::TYPE_REDIS:
                return $this->createRedis($options['host'] ?? 'localhost', $options['port'] ?? '6379');
            // pdo;db=mysql;host=localhost;dbname=lock;user=test;password=test
            case self::TYPE_PDO:
                return $this->createPdo($options['db'] ?? 'unknownDB',
                    $options['host'] ?? 'localhost',
                    $options['dbname'] ?? '',
                    $options['user'],
                    $options['password'] ?? '');
            default:
                return $this->createFlock(null);
        }
    }

    private function createFlock(?string $lockPath): FlockStore
    {
        return new FlockStore($lockPath);
    }

    private function createMemcached(string $host, string $port): MemcachedStore
    {
        return new MemcachedStore(MemcachedAdapter::createConnection("memcached://$host:$port"));
    }

    private function createRedis(string $host, string $port): RedisStore
    {
        return new RedisStore(RedisAdapter::createConnection("redis://$host:$port"));
    }

    private function createPdo(string $db, string $host, string $dbname, string $dbUser, string $dbPassword): PdoStore
    {
        return new PdoStore(
            "$db:host=$host;dbname=$dbname",
            ['db_username' => $dbUser, 'db_password' => $dbPassword]
        );
    }

    private function parse(string $dsn): array
    {
        $attrs = \explode(';', $dsn, 50);
        $type = $attrs[0] ?? self::TYPE_FLOCK;
        $options = [];
        if (\count($attrs) > 1) {
            foreach (\array_slice($attrs, 1) as $kv) {
                $kvItem = \explode('=', $kv, 2);
                if (2 === \count($kvItem)) {
                    $options[$kvItem[0]] = $kvItem[1];
                }
            }
        }
        return [
            'type' => $type,
            'options' => $options,
        ];
    }
}
