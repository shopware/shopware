<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FkFieldSerializer implements FieldSerializerInterface
{
    use FieldValidatorTrait;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function getFieldClass(): string
    {
        return FkField::class;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof FkField) {
            throw new InvalidSerializerFieldException(FkField::class, $field);
        }

        /** @var FkField $field */
        $value = $data->getValue();

        if ($this->shouldUseContext($field, $data)) {
            try {
                $value = $parameters->getContext()->get($field->getReferenceClass(), $field->getReferenceField());
            } catch (\InvalidArgumentException $exception) {
                $this->validate(
                    $this->validator,
                    $this->getConstraints($field, $existence),
                    $data->getKey(),
                    $value,
                    $parameters->getPath()
                );
            }
        }

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }

        yield $field->getStorageName() => Uuid::fromStringToBytes($value);
    }

    public function decode(Field $field, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }

    protected function shouldUseContext(FkField $field, KeyValuePair $data): bool
    {
        return $data->isRaw() && $data->getValue() === null && $field->is(Required::class);
    }

    protected function getConstraints(FkField $field, EntityExistence $existence): array
    {
        if ($field->is(Inherited::class) && $existence->isChild()) {
            return [];
        }

        if ($field->is(Required::class)) {
            return [new NotBlank()];
        }

        return [];
    }
}
