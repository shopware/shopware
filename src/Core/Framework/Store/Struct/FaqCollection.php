<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class FaqCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return FaqStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return FaqStruct::fromArray($element);
    }
}
