<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BoolFieldSerializer implements FieldSerializerInterface
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
        return BoolField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof BoolField) {
            throw new InvalidSerializerFieldException(BoolField::class, $field);
        }

        if (!$field->is(Inherited::class)) {
            $constraints = $this->constraintBuilder
                ->isBool()
                ->getConstraints();

            $this->validate($this->validator, $constraints, $data->getKey(), $data->getValue(), $parameters->getPath());
        }

        $transformed = $data->getValue() ? 1 : 0;

        /* @var BoolField $field */
        yield $field->getStorageName() => $transformed;
    }

    public function decode(Field $field, $value): bool
    {
        return (bool) $value;
    }
}
