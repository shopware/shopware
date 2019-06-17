<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceRuleEntity;

class PriceRulesJsonFieldSerializer implements FieldSerializerInterface
{
    /**
     * @var PriceRuleEntity
     */
    private $priceRuleEntity;

    /**
     * @var Price
     */
    private $priceStruct;

    public function __construct()
    {
        $this->priceRuleEntity = new PriceRuleEntity();
        $this->priceStruct = new Price(0, 0, true);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        throw new \RuntimeException('Price rules json field will be set by indexer');
    }

    public function decode(Field $field, $value): PriceRuleCollection
    {
        $value = json_decode((string) $value, true);

        $structs = [];
        if (isset($value['raw'])) {
            $structs = [];
            foreach ($value['raw'] as $raw) {
                $entity = clone $this->priceRuleEntity;

                $price = clone $this->priceStruct;
                $price->assign($raw['price']);

                $entity->setId($raw['id']);
                $entity->setRuleId($raw['ruleId']);
                $entity->setCurrencyId($raw['currencyId']);
                $entity->setPrice($price);
                $entity->setUniqueIdentifier($entity->getId());

                $structs[] = $entity;
            }
        }

        return new PriceRuleCollection($structs);
    }

    public static function convertToStorage($data): array
    {
        $queryOptimized = [];
        foreach ($data as $row) {
            $queryOptimized = array_merge_recursive(
                $queryOptimized,
                [
                    'r' . $row['ruleId'] => [
                        'c' . $row['currencyId'] => ['gross' => $row['price']['gross'], 'net' => $row['price']['net']],
                    ],
                ]
            );
        }

        return [
            'raw' => $data,
            'optimized' => $queryOptimized,
        ];
    }
}
