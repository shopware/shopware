<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\CustomField\CustomFieldService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldsSerializer extends JsonFieldSerializer
{
    public function __construct(
        DefinitionInstanceRegistry $definitionRegistry,
        ValidatorInterface $validator,
        private readonly CustomFieldService $attributeService
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof CustomFields) {
            throw DataAbstractionLayerException::invalidSerializerField(CustomFields::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        /** @var array<string, mixed> $attributes */
        $attributes = $data->getValue();
        if ($attributes === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if ($attributes === []) {
            yield $field->getStorageName() => '{}';

            return;
        }

        // set fields dynamically
        $field->setPropertyMapping($this->getFields(array_keys($attributes)));
        $encoded = $this->validateMapping($field, $attributes, $parameters);

        if ($encoded === []) {
            return;
        }

        if ($existence->exists()) {
            $this->extractJsonUpdate([$field->getStorageName() => $encoded], $existence, $parameters);

            return;
        }

        yield $field->getStorageName() => Json::encode($encoded);
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function decode(Field $field, mixed $value): array|object|null
    {
        if (!$field instanceof CustomFields) {
            throw DataAbstractionLayerException::invalidSerializerField(CustomFields::class, $field);
        }

        if ($value) {
            // set fields dynamically
            /** @var list<string> $attributes */
            $attributes = array_keys(json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR));

            $field->setPropertyMapping($this->getFields($attributes));
        }

        return parent::decode($field, $value);
    }

    /**
     * @param list<string> $attributeNames
     *
     * @return list<Field>
     */
    private function getFields(array $attributeNames): array
    {
        $fields = [];
        foreach ($attributeNames as $attributeName) {
            $fields[] = $this->attributeService->getCustomField($attributeName);
        }

        return $fields;
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function extractJsonUpdate(array $data, EntityExistence $existence, WriteParameterBag $parameters): void
    {
        $entityName = $existence->getEntityName();
        if (!$entityName) {
            return;
        }

        $definition = $this->definitionRegistry->getByEntityName($entityName);

        $pks = array_combine(
            array_keys($existence->getPrimaryKey()),
            array_map(
                static function (string $pkFieldStorageName) use ($definition, $existence, $parameters): mixed {
                    $pkFieldValue = $existence->getPrimaryKey()[$pkFieldStorageName];
                    $field = $definition->getFields()->getByStorageName($pkFieldStorageName);

                    if (!$field instanceof Field) {
                        return $pkFieldValue;
                    }

                    return $field->getSerializer()->encode(
                        $field,
                        $existence,
                        new KeyValuePair(key: $field->getPropertyName(), value: $pkFieldValue, isRaw: true),
                        $parameters,
                    )->current();
                },
                array_keys($existence->getPrimaryKey()),
            ),
        );

        foreach ($data as $storageName => $attributes) {
            $jsonUpdateCommand = new JsonUpdateCommand(
                definition: $definition,
                storageName: $storageName,
                payload: $attributes,
                primaryKey: $pks,
                existence: $existence,
                path: $parameters->getPath()
            );

            $identifier = WriteCommandQueue::hashedPrimary(
                $this->definitionRegistry,
                $jsonUpdateCommand,
            );

            $parameters->getCommandQueue()->add(
                $jsonUpdateCommand->getEntityName(),
                $identifier,
                $jsonUpdateCommand
            );
        }
    }
}
