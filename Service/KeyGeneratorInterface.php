<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

interface KeyGeneratorInterface
{
    public function isKeyDynamic(string $key): bool;
    public function normalize(string $key): string;

    /**
     * @param string $key
     * @param Request $request
     * @param string|callable $keyHashFunc
     * @return string
     */
    public function generate(string $key, Request $request, $keyHashFunc): string;
}
