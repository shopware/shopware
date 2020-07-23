<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ListingPriceFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        throw new \RuntimeException('Price rules json field will be set by indexer');
    }

    public function decode(Field $field, $value): ListingPriceCollection
    {
        if (!$value) {
            return new ListingPriceCollection();
        }

        $value = json_decode((string) $value, true);

        if (!array_key_exists('structs', $value)) {
            return new ListingPriceCollection();
        }

        return new ListingPriceCollection(unserialize($value['structs']));
    }
}
