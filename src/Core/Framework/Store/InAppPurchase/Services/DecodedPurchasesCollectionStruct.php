<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Validation\ValidatorFactory;

/**
 * @internal
 *
 * @template-extends Collection<DecodedPurchaseStruct>
 */
#[Package('checkout')]
class DecodedPurchasesCollectionStruct extends Collection
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $elements['elements'] = \array_map(static function (array $element): DecodedPurchaseStruct {
            $dto = ValidatorFactory::create($element, DecodedPurchaseStruct::class);
            if (!$dto instanceof DecodedPurchaseStruct) {
                throw StoreException::invalidType(DecodedPurchaseStruct::class, $dto::class);
            }

            return $dto;
        }, $data);

        return (new self())->assign($elements);
    }
}
