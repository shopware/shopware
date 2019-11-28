<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Symfony\Component\HttpFoundation\Request;

class RequestInheritableAttributes implements RequestInheritableAttributesInterface
{
    public function extract(Request $sourceRequest): array
    {
        $inheritableAttributes = [];
        foreach (RequestInheritableAttributesInterface::INHERITABLE_ATTRIBUTE_NAMES as $attributeName) {
            if (!$sourceRequest->attributes->has($attributeName)) {
                continue;
            }

            $inheritableAttributes[$attributeName] = $sourceRequest->attributes->get($attributeName);
        }

        return $inheritableAttributes;
    }
}
