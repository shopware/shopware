<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DateFieldSerializer implements FieldSerializerInterface
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
        return DateField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof DateField) {
            throw new InvalidSerializerFieldException(DateField::class, $field);
        }

        $value = $data->getValue();

        if (\is_string($value)) {
            $value = new \DateTime($value);
        }

        if (is_array($value) && array_key_exists('date', $value)) {
            $value = new \DateTime($value['date']);
        }

        /** @var DateField $field */
        if ($this->requiresValidation($field, $existence, $value, $parameters)) {
            $constraints = $this->constraintBuilder
                ->isDate()
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $value, $parameters->getPath());
        }

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        yield $field->getStorageName() => $value->format(Defaults::DATE_FORMAT);
    }

    public function decode(Field $field, $value)
    {
        return $value === null ? null : new \DateTime($value);
    }
}
