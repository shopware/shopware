<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PriceFieldSerializer extends AbstractFieldSerializer
{
    /**
     * @var Price
     */
    private $blueprint;

    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
        $this->blueprint = new Price('', 0, 0, true, null);
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof PriceField) {
            throw new InvalidSerializerFieldException(PriceField::class, $field);
        }

        $value = $data->getValue();

        /** @var JsonField $field */
        if ($this->requiresValidation($field, $existence, $value, $parameters)) {
            if ($value !== null) {
                foreach ($value as &$row) {
                    unset($row['extensions']);
                }
            }
            $data->setValue($value);

            if ($field->is(Required::class)) {
                $this->validate([new NotBlank()], $data, $parameters->getPath());
            }

            $constraints = $this->getCachedConstraints($field);
            $pricePath = $parameters->getPath() . '/price';

            foreach ($data->getValue() as $index => $price) {
                $this->validate($constraints, new KeyValuePair((string) $index, $price, true), $pricePath);
            }

            $converted = [];

            foreach ($value as $price) {
                $price['gross'] = (float) $price['gross'];
                $price['net'] = (float) $price['net'];

                $converted['c' . $price['currencyId']] = $price;
            }
            $value = $converted;
        }

        if ($value !== null) {
            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        // used for nested hydration (example cheapest-price-hydrator)
        if (\is_string($value)) {
            $value = json_decode($value, true);
        }

        $prices = [];
        foreach ($value as $row) {
            $price = clone $this->blueprint;
            $price->setCurrencyId($row['currencyId']);
            $price->setNet((float) $row['net']);
            $price->setGross((float) $row['gross']);
            $price->setLinked((bool) $row['linked']);

            if (isset($row['listPrice']) && isset($row['listPrice']['gross'])) {
                $data = $row['listPrice'];

                $listPrice = clone $this->blueprint;
                $listPrice->setCurrencyId($row['currencyId']);
                $listPrice->setNet((float) $data['net']);
                $listPrice->setGross((float) $data['gross']);
                $listPrice->setLinked((bool) $data['linked']);
                $price->setListPrice($listPrice);
            }

            $prices[] = $price;
        }

        return new PriceCollection($prices);
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new Collection([
                'allowExtraFields' => true,
                'allowMissingFields' => false,
                'fields' => [
                    'currencyId' => [new NotBlank(), new Uuid()],
                    'gross' => [new NotBlank(), new Type(['numeric'])],
                    'net' => [new NotBlank(), new Type(['numeric'])],
                    'linked' => [new Type('boolean')],
                    'listPrice' => [
                        new Optional(
                            new Collection([
                                'allowExtraFields' => true,
                                'allowMissingFields' => false,
                                'fields' => [
                                    'gross' => [new NotBlank(), new Type(['numeric'])],
                                    'net' => [new NotBlank(), new Type('numeric')],
                                    'linked' => [new Type('boolean')],
                                ],
                            ])
                        ),
                    ],
                ],
            ]),
        ];

        return $constraints;
    }
}
