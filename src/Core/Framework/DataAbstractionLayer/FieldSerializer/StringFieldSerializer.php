<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StringFieldSerializer implements FieldSerializerInterface
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

    public function getFieldClass(): string
    {
        return StringField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StringField) {
            throw new InvalidSerializerFieldException(StringField::class, $field);
        }

        $value = $data->getValue();
        if ($value === '') {
            $value = null;
        }

        if ($this->requiresValidation($field, $existence, $value, $parameters)) {
            $constraints = $this->constraintBuilder
                ->isNotBlank()
                ->isString()
                ->isLengthLessThanOrEqual($field->getMaxLength())
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $value, $parameters->getPath());
        }

        /* @var LongTextField $field */
        yield $field->getStorageName() => $value !== null ? strip_tags((string) $value) : null;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
