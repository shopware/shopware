<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

class RequestTransformer implements RequestTransformerInterface
{
    public function transform(Request $request): Request
    {
        return $request;
    }

    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        return [];
    }
}
