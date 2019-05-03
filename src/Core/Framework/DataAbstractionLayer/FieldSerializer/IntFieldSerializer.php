<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class IntFieldSerializer implements FieldSerializerInterface
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

    public function __construct(ConstraintBuilder $constraintBuilder, ValidatorInterface $validator)
    {
        $this->constraintBuilder = $constraintBuilder;
        $this->validator = $validator;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof IntField) {
            throw new InvalidSerializerFieldException(IntField::class, $field);
        }
        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $constraintBuilder = $this->constraintBuilder
                ->isInt()
                ->isNotBlank();

            if ($field->getMinValue() !== null) {
                $constraintBuilder->isGreaterThanOrEqual($field->getMinValue());
            }

            if ($field->getMaxValue() !== null) {
                $constraintBuilder->isLessThanOrEqual($field->getMaxValue());
            }

            $constraints = $constraintBuilder->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        /* @var IntField $field */
        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
