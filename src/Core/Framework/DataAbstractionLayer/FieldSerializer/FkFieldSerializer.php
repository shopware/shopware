<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid as UuidConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;

class FkFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof FkField) {
            throw new InvalidSerializerFieldException(FkField::class, $field);
        }

        $value = $data->getValue();

        if ($this->shouldUseContext($field, $data)) {
            try {
                $value = $parameters->getContext()->get($field->getReferenceDefinition()->getClass(), $field->getReferenceField());
            } catch (\InvalidArgumentException $exception) {
                if ($this->requiresValidation($field, $existence, $value, $parameters)) {
                    $this->validate($this->getConstraints($field), $data, $parameters->getPath());
                }
            }
        }

        if ($value === null) {
            yield $field->getStorageName() => null;

            return;
        }
        if ($this->requiresValidation($field, $existence, $value, $parameters)) {
            $this->validate([new UuidConstraint()], $data, $parameters->getPath());
        }

        if ($value !== null) {
            $value = Uuid::fromHexToBytes($value);
        }

        yield $field->getStorageName() => $value;
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

    protected function getConstraints(Field $field): array
    {
        return [new NotBlank()];
    }
}
