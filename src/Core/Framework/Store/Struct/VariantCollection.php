<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 *
 * @package inventory
 */
class VariantCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return VariantStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return VariantStruct::fromArray($element);
    }
}
