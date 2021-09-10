<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
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
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PriceFieldSerializer extends AbstractFieldSerializer
{
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator
    ) {
        parent::__construct($validator, $definitionRegistry);
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

            $this->ensureDefaultPrice($parameters, $data->getValue());

            $converted = [];

            foreach ($value as $price) {
                $price['gross'] = (float) $price['gross'];
                $price['net'] = (float) $price['net'];

                if (isset($price['listPrice'])) {
                    $price['percentage'] = null;
                }

                if (($price['listPrice']['net'] ?? 0) > 0 || ($price['listPrice']['gross'] ?? 0) > 0) {
                    $price['percentage'] = [
                        'net' => 0.0,
                        'gross' => 0.0,
                    ];

                    if (($price['listPrice']['net'] ?? 0) > 0) {
                        $price['percentage']['net'] = round(100 - $price['net'] / $price['listPrice']['net'] * 100, 2);
                    }

                    if (($price['listPrice']['gross'] ?? 0) > 0) {
                        $price['percentage']['gross'] = round(100 - $price['gross'] / $price['listPrice']['gross'] * 100, 2);
                    }
                }

                $converted['c' . $price['currencyId']] = $price;
            }
            $value = $converted;
        }

        if ($value !== null) {
            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    /**
     * @return PriceCollection|null
     *
     * @deprecated tag:v6.5.0 The parameter $value and return type will be native typed
     */
    public function decode(Field $field, /*?string */$value)/*: ?PriceCollection*/
    {
        if ($value === null) {
            return null;
        }

        // used for nested hydration (example cheapest-price-hydrator)
        if (\is_string($value)) {
            $value = json_decode($value, true);
        }

        $collection = new PriceCollection();

        foreach ($value as $row) {
            if (!isset($row['listPrice']) || !isset($row['listPrice']['gross'])) {
                $collection->add(
                    new Price($row['currencyId'], (float) $row['net'], (float) $row['gross'], (bool) $row['linked'])
                );

                continue;
            }

            $data = $row['listPrice'];

            $collection->add(
                new Price(
                    $row['currencyId'],
                    (float) $row['net'],
                    (float) $row['gross'],
                    (bool) $row['linked'],
                    new Price(
                        $row['currencyId'],
                        (float) $data['net'],
                        (float) $data['gross'],
                        (bool) $data['linked'],
                    ),
                    $row['percentage'] ?? null
                )
            );
        }

        return $collection;
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

    private function ensureDefaultPrice(WriteParameterBag $parameters, array $prices): void
    {
        foreach ($prices as $price) {
            if ($price['currencyId'] === Defaults::CURRENCY) {
                return;
            }
        }

        $violationList = new ConstraintViolationList();
        $violationList->add(
            new ConstraintViolation(
                'No price for default currency defined',
                'No price for default currency defined',
                [],
                '',
                '/price',
                $prices
            )
        );

        throw new WriteConstraintViolationException($violationList, $parameters->getPath());
    }
}
