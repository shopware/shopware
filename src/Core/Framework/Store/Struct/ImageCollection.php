<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Deprecation\BCChange\ReturnTypeNarrowing;
use Shopware\Core\Framework\Log\Package;

/**
 * @template-extends StoreCollection<ImageStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class ImageCollection extends StoreCollection
{
    #[ReturnTypeNarrowing(version: 'v6.8.0', newType: 'string')]
    protected function getExpectedClass(): ?string
    {
        return ImageStruct::class;
    }

    protected function getElementFromArray(array $element): StoreStruct
    {
        return ImageStruct::fromArray($element);
    }
}
