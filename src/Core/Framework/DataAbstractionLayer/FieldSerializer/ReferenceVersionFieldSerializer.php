<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;

class ReferenceVersionFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field instanceof ReferenceVersionField) {
            throw new InvalidSerializerFieldException(ReferenceVersionField::class, $field);
        }

        $value = $data[$field->getPropertyName()] ?? null;
        if ($value === null && !$field->is(Required::class)) {
            return $data;
        }

        $definition = $parameters->getDefinition();

        $reference = $field->getVersionReferenceDefinition();

        $context = $parameters->getContext();

        if ($value !== null || $definition === $reference) {
            // parent inheritance with versioning
            $value ??= Defaults::LIVE_VERSION;
        } elseif ($context->has($reference->getClass(), 'versionId')) {
            // if the reference is already written, use the version id of the written entity
            $value = $context->get($reference->getClass(), 'versionId');
        } elseif ($definition->getParentDefinition() === $reference && $context->has($definition->getClass(), 'versionId')) {
            // if the current entity is a sub entity (e.g. order -> line-item)
            // and the version id isn't set, use the same version id of the own entity
            // this is the case, if a entity is created over a sub api call
            $value = $context->get($definition->getClass(), 'versionId');
        } else {
            $value = Defaults::LIVE_VERSION;
        }

        $data[$field->getPropertyName()] = $value;

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof ReferenceVersionField) {
            throw new InvalidSerializerFieldException(ReferenceVersionField::class, $field);
        }

        $definition = $parameters->getDefinition();

        $reference = $field->getVersionReferenceDefinition();

        $context = $parameters->getContext();

        if ($data->getValue() !== null || $definition === $reference) {
            // parent inheritance with versioning
            $value = $data->getValue() ?? Defaults::LIVE_VERSION;
        } elseif ($context->has($reference->getClass(), 'versionId')) {
            // if the reference is already written, use the version id of the written entity
            $value = $context->get($reference->getClass(), 'versionId');
        } elseif ($definition->getParentDefinition() === $reference && $context->has($definition->getClass(), 'versionId')) {
            // if the current entity is a sub entity (e.g. order -> line-item)
            // and the version id isn't set, use the same version id of the own entity
            // this is the case, if a entity is created over a sub api call
            $value = $context->get($definition->getClass(), 'versionId');
        } else {
            $value = Defaults::LIVE_VERSION;
        }

        yield $field->getStorageName() => Uuid::fromHexToBytes($value);
    }

    /**
     * @deprecated tag:v6.5.0 The parameter $value will be native typed
     */
    public function decode(Field $field, /*?string */$value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
