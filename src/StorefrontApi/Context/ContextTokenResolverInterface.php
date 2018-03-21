<?php

namespace Shopware\StorefrontApi\Context;

use Symfony\Component\HttpFoundation\Request;

interface ContextTokenResolverInterface
{
    public function resolve(Request $request): string;
}