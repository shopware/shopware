<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\System\CustomField\CustomFieldService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomFieldsSerializer extends JsonFieldSerializer
{
    /**
     * @var WriteCommandExtractor
     */
    private $writeExtractor;

    /**
     * @var CustomFieldService
     */
    private $attributeService;

    public function __construct(
        DefinitionInstanceRegistry $compositeHandler,
        ValidatorInterface $validator,
        CustomFieldService $attributeService,
        WriteCommandExtractor $writeExtractor
    ) {
        parent::__construct($compositeHandler, $validator);
        $this->attributeService = $attributeService;
        $this->writeExtractor = $writeExtractor;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof CustomFields) {
            throw new InvalidSerializerFieldException(CustomFields::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        $attributes = $data->getValue();
        if ($attributes === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if (empty($attributes)) {
            yield $field->getStorageName() => '{}';

            return;
        }

        // set fields dynamically
        $field->setPropertyMapping($this->getFields(array_keys($attributes)));
        $encoded = $this->validateMapping($field, $attributes, $parameters);

        if (empty($encoded)) {
            return;
        }

        if ($existence->exists()) {
            $this->writeExtractor->extractJsonUpdate([$field->getStorageName() => $encoded], $existence, $parameters);

            return;
        }

        yield $field->getStorageName() => parent::encodeJson($encoded);
    }

    /**
     * @return array|null
     *
     * @deprecated tag:v6.5.0 The parameter $value and return type will be native typed
     */
    public function decode(Field $field, /*?string */$value)/*: ?array*/
    {
        if (!$field instanceof CustomFields) {
            throw new InvalidSerializerFieldException(CustomFields::class, $field);
        }

        if ($value) {
            // set fields dynamically
            $field->setPropertyMapping($this->getFields(array_keys(json_decode($value, true))));
        }

        return parent::decode($field, $value);
    }

    private function getFields(array $attributeNames): array
    {
        $fields = [];
        foreach ($attributeNames as $attributeName) {
            $fields[] = $this->attributeService->getCustomField($attributeName)
                ?? new JsonField($attributeName, $attributeName);
        }

        return $fields;
    }
}
