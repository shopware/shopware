<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class RequestTransformer implements RequestTransformerInterface
{
    public function transform(Request $request): Request
    {
        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        return [];
    }
}
