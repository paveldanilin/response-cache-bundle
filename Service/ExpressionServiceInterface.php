<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

interface ExpressionServiceInterface
{
    /**
     * @param string $expression
     * @param array $context
     * @return mixed
     */
    public function evaluate(string $expression, array $context = []);

    /**
     * @param string $expression
     * @param Request $request
     * @return mixed
     */
    public function evaluateOnRequest(string $expression, Request $request);
}
