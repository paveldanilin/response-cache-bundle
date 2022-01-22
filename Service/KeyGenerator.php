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

    /**
     * @param string $key
     * @param Request $request
     * @param string|callable $keyHashFunc
     * @return string
     */
    public function generate(string $key, Request $request, $keyHashFunc): string
    {
        if ($this->isKeyDynamic($key)) {
            $k = (string)$this->expression->evaluateOnRequest($this->normalize($key), $request);
        } else {
            $k = $key; // Static key
        }
        return $this->createHash($k, $keyHashFunc);
    }

    public function isKeyDynamic(string $key): bool
    {
        return !empty($key) && '#' === $key[0];
    }

    public function normalize(string $key): string
    {
        return \ltrim($key, '#');
    }

    /**
     * @param string $key
     * @param string|callable $hashFunc
     * @return string
     */
    private function createHash(string $key, $hashFunc): string
    {
        if (empty($hashFunc) || !\is_callable($hashFunc)) {
            return $key;
        }
        return $hashFunc($key);
    }
}
