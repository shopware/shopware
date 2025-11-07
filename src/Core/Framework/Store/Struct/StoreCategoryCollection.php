<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @template-extends StoreCollection<StoreCategoryStruct>
 */
#[Package('checkout')]
class StoreCategoryCollection extends StoreCollection
{
    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will only return string
     */
    protected function getExpectedClass(): ?string
    {
        return StoreCategoryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return StoreCategoryStruct::fromArray($element);
    }
}
