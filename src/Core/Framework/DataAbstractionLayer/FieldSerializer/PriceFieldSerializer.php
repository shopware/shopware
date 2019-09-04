<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Pricing\Price;
use Shopware\Core\Framework\Pricing\PriceCollection;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
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

            $constraints = $this->getConstraints($field);

            $this->validate($constraints, $data, $parameters->getPath());

            $converted = [];

            foreach ($value as $price) {
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
        $value = json_decode($value, true);

        $prices = [];
        foreach ($value as $row) {
            $prices[] = new Price($row['currencyId'], (float) $row['net'], (float) $row['gross'], (bool) $row['linked']);
        }

        return new PriceCollection($prices);
    }

    protected function getConstraints(Field $field): array
    {
        $constraints = [
            new All([
                'constraints' => new Collection([
                    'allowExtraFields' => false,
                    'allowMissingFields' => false,
                    'fields' => [
                        'currencyId' => [new NotBlank(), new Uuid()],
                        'gross' => [new NotBlank(), new Type('numeric')],
                        'net' => [new NotBlank(), new Type('numeric')],
                        'linked' => [new Type('boolean')],
                    ],
                ]),
            ]),
        ];

        if ($field->is(Required::class)) {
            $constraints[] = new NotBlank();
        }

        return $constraints;
    }
}
