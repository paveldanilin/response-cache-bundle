<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

final class KeyHashGenerator implements KeyHashGeneratorInterface
{
    private array $ignoreRequestHeaders = ['host', 'user-agent'];

    private ExpressionRequestAwareInterface $expression;

    public function __construct(ExpressionRequestAwareInterface $expression)
    {
        $this->expression = $expression;
    }

    public function generate(string $key, Request $request): string
    {
        if ($this->isKeyDynamic($key)) {
            $k = (string)$this->expression->evaluateOnRequest($this->normalize($key), $request);
        } else if (empty($key)) {
            $k = $this->stringifyRequest($request);
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

    private function stringifyRequest(Request $request): string
    {
        $headers = $request->headers->all();
        foreach ($this->ignoreRequestHeaders as $ignoreRequestHeader) {
            unset($headers[$ignoreRequestHeader]);
        }
        \ksort($headers);
        return \implode('|', [
            $request->getMethod(),
            $request->getRequestUri(),
            \http_build_query($headers),
        ]);
    }
}
