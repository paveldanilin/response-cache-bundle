<?php

namespace Pada\ResponseCacheBundle\Service;

use Symfony\Component\HttpFoundation\Request;

interface ExpressionRequestAwareInterface
{
    /**
     * @param string $expression
     * @param Request $request
     * @return mixed
     */
    public function evaluateOnRequest(string $expression, Request $request);
}
