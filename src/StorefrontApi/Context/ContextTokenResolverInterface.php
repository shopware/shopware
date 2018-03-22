<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Symfony\Component\HttpFoundation\Request;

interface ContextTokenResolverInterface
{
    public function resolve(Request $request): string;
}
