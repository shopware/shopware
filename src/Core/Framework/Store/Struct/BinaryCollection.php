<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class BinaryCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return BinaryStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return BinaryStruct::fromArray($element);
    }
}
