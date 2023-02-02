<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @codeCoverageIgnore
 */
class ImageCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return ImageStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ImageStruct::fromArray($element);
    }
}
