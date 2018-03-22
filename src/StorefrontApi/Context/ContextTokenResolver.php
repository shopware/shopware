<?php

namespace Shopware\StorefrontApi\Context;

use Shopware\Framework\Struct\Uuid;
use Symfony\Component\HttpFoundation\Request;

class ContextTokenResolver implements ContextTokenResolverInterface
{
    public function resolve(Request $request): string
    {
        if ($request->headers->has(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY)) {
            return $request->headers->get(StorefrontContextValueResolver::CONTEXT_TOKEN_KEY);
        }
        return Uuid::uuid4()->getHex();
    }
}