<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Pricing\PriceRuleCollection;
use Shopware\Core\Framework\Pricing\PriceRuleEntity;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PriceRulesJsonFieldSerializer implements FieldSerializerInterface
{
    use FieldValidatorTrait;

    /**
     * @var ConstraintBuilder
     */
    protected $constraintBuilder;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    public function __construct(
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator,
        SerializerInterface $serializer
    ) {
        $this->constraintBuilder = $constraintBuilder;
        $this->validator = $validator;
        $this->serializer = $serializer;
    }

    public function getFieldClass(): string
    {
        return PriceRulesJsonField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof PriceRulesJsonField) {
            throw new InvalidSerializerFieldException(PriceRulesJsonField::class, $field);
        }
        $value = $data->getValue();
        if (!empty($value)) {
            $value = self::convertToStorage($value);
        }

        /** @var PriceRulesJsonField $field */
        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $constraints = $this->constraintBuilder
                ->addConstraint(new Type('array'))
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        if (!\is_string($value) && $value !== null) {
            $value = JsonFieldSerializer::encodeJson($value);
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value): PriceRuleCollection
    {
        $value = json_decode((string) $value, true);

        $structs = [];
        if (isset($value['raw'])) {
            foreach ($value['raw'] as $record) {
                /** @var PriceRuleEntity $struct */
                $struct = $this->serializer->deserialize(json_encode($record), '', 'json');
                $struct->setUniqueIdentifier($struct->getId());
                $structs[] = $struct;
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
