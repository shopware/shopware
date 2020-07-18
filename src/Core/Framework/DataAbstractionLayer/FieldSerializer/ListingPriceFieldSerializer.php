<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class ListingPriceFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @var ListingPrice
     */
    private $listPrice;

    /**
     * @var Price
     */
    private $price;

    public function __construct()
    {
        $this->listPrice = new ListingPrice();
        $this->price = new Price('', 0, 0, false);
    }

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

        $value = \json_decode((string) $value, true);

        $structs = [];
        foreach ($value as $ruleId => $rows) {
            if ($ruleId === 'default') {
                $ruleId = null;
            } else {
                $ruleId = \mb_substr($ruleId, 1);
            }

            foreach ($rows as $row) {
                $from = clone $this->price;
                $from->assign($row['from']);

                $to = clone $this->price;
                $to->assign($row['to']);

                $price = clone $this->listPrice;
                $price->assign([
                    'ruleId' => $ruleId,
                    'currencyId' => $row['currencyId'],
                    'from' => $from,
                    'to' => $to,
                ]);

                $structs[] = $price;
            }
        }

        return new ListingPriceCollection($structs);
    }
}
