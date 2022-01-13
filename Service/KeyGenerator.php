<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

final class KeyGenerator implements KeyGeneratorInterface
{
    private ExpressionServiceInterface $expression;

    public function __construct(ExpressionServiceInterface $expression)
    {
        $this->expression = $expression;
    }

    public function generate(string $key, Request $request): string
    {
        if ($this->isKeyDynamic($key)) {
            $k = (string)$this->expression->evaluateOnRequest($this->normalize($key), $request);
        } else {
            $k = $key; // Static key
        }
        return \md5($k);
    }

    public function isKeyDynamic(string $key): bool
    {
        return !empty($key) && '#' === $key[0];
    }

    public function normalize(string $key): string
    {
        return \ltrim($key, '#');
    }
}
