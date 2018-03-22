<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Context;

use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Firewall\ApplicationAuthenticator;
use Symfony\Component\HttpFoundation\Request;

class ContextTokenResolver implements ContextTokenResolverInterface
{
    public function resolve(Request $request): string
    {
        if ($request->headers->has(ApplicationAuthenticator::CONTEXT_TOKEN_KEY)) {
            return $request->headers->get(ApplicationAuthenticator::CONTEXT_TOKEN_KEY);
        }

        return Uuid::uuid4()->getHex();
    }
}
