<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Generator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PasswordFieldSerializer implements FieldSerializerInterface
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
        return PasswordField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): Generator {
        if (!$field instanceof PasswordField) {
            throw new InvalidSerializerFieldException(PasswordField::class, $field);
        }
        /** @var PasswordField $field */
        if ($this->requiresValidation($field, $existence, $data->getValue(), $parameters)) {
            $constraints = $this->constraintBuilder
                ->isNotBlank()
                ->isString()
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        $value = $data->getValue();
        if ($value) {
            $value = password_hash($value, $field->getAlgorithm(), $field->getHashOptions());
        }

        yield $field->getStorageName() => $value;
    }

    public function decode(Field $field, $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
