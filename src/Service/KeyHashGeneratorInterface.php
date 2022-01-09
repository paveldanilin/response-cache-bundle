<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

interface KeyHashGeneratorInterface
{
    public function isKeyDynamic(string $key): bool;
    public function normalize(string $key): string;
    public function generate(string $key, Request $request): string;
}
