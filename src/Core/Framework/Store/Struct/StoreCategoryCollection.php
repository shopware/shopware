<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class StoreCategoryCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return StoreCategoryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return StoreCategoryStruct::fromArray($element);
    }
}
