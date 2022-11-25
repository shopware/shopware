<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

/**
 * @package merchant-services
 *
 * @codeCoverageIgnore
 */
class ReviewCollection extends StoreCollection
{
    protected function getExpectedClass(): ?string
    {
        return ReviewStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ReviewStruct::fromArray($element);
    }
}
