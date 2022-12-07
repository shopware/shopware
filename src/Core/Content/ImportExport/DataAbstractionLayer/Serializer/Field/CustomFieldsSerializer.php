<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer as DalCustomFieldsSerializer;
use Shopware\Core\System\CustomField\CustomFieldService;

class CustomFieldsSerializer extends FieldSerializer
{
    private DalCustomFieldsSerializer $customFieldsSerializer;

    private CustomFieldService $customFieldService;

    /**
     * @internal
     */
    public function __construct(DalCustomFieldsSerializer $customFieldsSerializer, CustomFieldService $customFieldService)
    {
        $this->customFieldsSerializer = $customFieldsSerializer;
        $this->customFieldService = $customFieldService;
    }

    /**
     * @param mixed|null $value
     */
    public function serialize(Config $config, Field $field, $value): iterable
    {
        if (!$field instanceof CustomFields) {
            throw new \InvalidArgumentException('Expected field to be an instance of ' . CustomFields::class);
        }

        if (!\is_array($value)) {
            yield $field->getPropertyName() => $value;

            return;
        }

        ksort($value);

        yield $field->getPropertyName() => json_encode($value);

        foreach ($value as $customFieldKey => $customFieldValue) {
            $customFieldValue = \is_array($customFieldValue) ? json_encode($customFieldValue) : $customFieldValue;

            yield $field->getPropertyName() . '.' . $customFieldKey => $customFieldValue;
        }
    }

    /**
     * @param mixed|null $value
     *
     * @return mixed|null
     */
    public function deserialize(Config $config, Field $field, $value)
    {
        if (!$field instanceof CustomFields) {
            throw new \InvalidArgumentException('Expected CustomFields');
        }

        if (!\is_array($value)) {
            return parent::deserialize($config, $field, $value);
        }

        $customFieldsFromJson = null;

        // if contents of customFields is imported directly as raw json, it is stored without key -
        // retrieve the raw json to decode it and unset it from specific customField values -
        // merge it with values for customFields imported with specific keys if the latter exist
        if (isset($value[0]) && \is_string($value[0])) {
            /** @var array<mixed>|null $customFieldsFromJson */
            $customFieldsFromJson = $this->customFieldsSerializer->decode($field, $value[0]);

            unset($value[0]);
        }

        $customFields = $this->decodeCustomFields($value, $field);

        if (!$customFieldsFromJson) {
            return $customFields;
        }

        if ($customFields) {
            return array_merge($customFieldsFromJson, $customFields);
        }

        return $customFieldsFromJson;
    }

    public function supports(Field $field): bool
    {
        return $field instanceof CustomFields;
    }

    private function decodeCustomFields(array $customFields, Field $field): ?array
    {
        $customFields = json_encode(array_filter($customFields, function ($value) {
            return $value !== '';
        }));

        if (!$customFields) {
            return null;
        }

        $customFields = $this->customFieldsSerializer->decode($field, $customFields);

        if (!\is_array($customFields)) {
            return null;
        }

        foreach ($customFields as $key => $value) {
            if (!$this->customFieldService->getCustomField($key) instanceof JsonField) {
                continue;
            }

            $jsonDecoded = json_decode($value);

            if (\is_array($jsonDecoded)) {
                $customFields[$key] = $jsonDecoded;
            }
        }

        return $customFields;
    }
}
