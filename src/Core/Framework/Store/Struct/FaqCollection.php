<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<FaqStruct>
 */
#[Package('checkout')]
class FaqCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return FaqStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return FaqStruct::fromArray($element);
    }
}
