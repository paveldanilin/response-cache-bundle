<?php

namespace Pada\ResponseCacheBundle\Service;

interface ExpressionInterface
{
    /**
     * @param string $expression
     * @param array $context
     * @return mixed
     */
    public function evaluate(string $expression, array $context = []);
}
