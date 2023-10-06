<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer as DalCustomFieldsSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldService;

#[Package('core')]
class CustomFieldsSerializer extends FieldSerializer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DalCustomFieldsSerializer $customFieldsSerializer,
        private readonly CustomFieldService $customFieldService
    ) {
    }

    /**
     * @param mixed|null $value
     *
     * @return iterable<string, mixed>
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

        yield $field->getPropertyName() => json_encode($value, \JSON_THROW_ON_ERROR);

        foreach ($value as $customFieldKey => $customFieldValue) {
            $customFieldValue = \is_array($customFieldValue) ? json_encode($customFieldValue, \JSON_THROW_ON_ERROR) : $customFieldValue;

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

    /**
     * @param array<string, mixed> $customFields
     *
     * @return array<string, mixed>|null
     */
    private function decodeCustomFields(array $customFields, Field $field): ?array
    {
        $customFields = json_encode(array_filter($customFields, fn ($value) => $value !== ''), \JSON_THROW_ON_ERROR);

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

            $jsonDecoded = json_decode((string) $value);

            if (\is_array($jsonDecoded)) {
                $customFields[$key] = $jsonDecoded;
            }
        }

        return $customFields;
    }
}
