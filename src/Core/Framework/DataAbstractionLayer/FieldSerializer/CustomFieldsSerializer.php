<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\CustomField\CustomFieldService;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CustomFieldsSerializer extends JsonFieldSerializer
{
    /** @var WriteCommandExtractor */
    private $writeExtractor;

    /**
     * @var CustomFieldService
     */
    private $attributeService;

    public function __construct(
        FieldSerializerRegistry $compositeHandler,
        ConstraintBuilder $constraintBuilder,
        ValidatorInterface $validator,
        CustomFieldService $attributeService,
        WriteCommandExtractor $writeExtractor
    ) {
        parent::__construct($compositeHandler, $constraintBuilder, $validator);
        $this->attributeService = $attributeService;
        $this->writeExtractor = $writeExtractor;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof CustomFields) {
            throw new InvalidSerializerFieldException(CustomFields::class, $field);
        }

        $attributes = $data->getValue();

        /** @var CustomFields $field */
        if ($this->requiresValidation($field, $existence, $attributes, $parameters)) {
            $constraints = $this->getConstraints($parameters);

            $this->validate($this->validator, $constraints, $data->getKey(), $attributes, $parameters->getPath());
        }

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

    public function decode(Field $field, $value)
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

    public function getFieldClass(): string
    {
        return CustomFields::class;
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
