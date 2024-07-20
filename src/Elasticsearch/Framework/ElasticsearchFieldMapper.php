<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Doctrine\DBAL\Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;

#[Package('buyers-experience')]
class ElasticsearchFieldMapper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexingUtils $indexingUtils
    ) {
    }

    /**
     * Maps an array of items to a language-specific array of translated values for a given field.
     *
     * This method is typically used in the context of product definitions, where each item represents a database record to be indexed in Elasticsearch.
     *
     * @param string $field The field to be translated.
     * @param array<int|string, array<string, string|null>> $items An array of items with language information.
     * @param array<int|string, array<string, string|null>> $fallbackItems An array of fallback items to inherit values from if the child item has null values.
     * @param bool $stripText (Optional) Indicates whether to strip text values.
     *
     * @return array<string, string|null> An array where language IDs are keys and translated values are values.
     *
     * @example
     *
     *  ```php
     *  $items = [['name' => 'foo', 'languageId' => 'de-DE'], ['name' => null, 'languageId' => 'en-GB']];
     *  // $fallbackItems is used for inheritance of values from parent to child if child value is null
     *  $fallbackItems = [['name' => 'foo-baz', 'languageId' => 'de-DE'], ['name' => 'bar', 'languageId' => 'en-GB'], ['name' => 'baz', 'languageId' => 'vi-VN'];
     *
     *  $esValue = ElasticsearchIndexingHelper::mapTranslatedField('name', $items, $fallbackItems);
     *  // ['de-DE' => 'foo', 'en-GB' => 'bar', 'vi-VN' => 'baz']
     *  ```
     */
    public static function translated(string $field, array $items, array $fallbackItems = [], bool $stripText = true): array
    {
        $value = [];
        $fallbackValue = [];

        // make sure every languages from parent also indexed
        $items = array_merge($fallbackItems, $items);

        foreach ($fallbackItems as $item) {
            if (empty($item['languageId'])) {
                continue;
            }

            $fallbackValue[$item['languageId']] = $item[$field] ?? null;
        }

        foreach ($items as $item) {
            $languageId = $item['languageId'] ?? Defaults::LANGUAGE_SYSTEM;

            // if child value is null, it should be inherited from parent
            $newValue = $item[$field] ?? ($fallbackValue[$languageId] ?? null);

            if ($stripText && \is_string($newValue) && $newValue !== '') {
                $newValue = ElasticsearchIndexingUtils::stripText($newValue);
            }

            $value[$languageId] = $newValue;
        }

        return $value;
    }

    /**
     * Maps an array of items to nested arrays of multilingual fields, each field is an array of multilingual values keyed by language ID.
     *
     * This method is commonly used to handle associations, such as product names and descriptions in different languages.
     *
     * @param array<int, array{id: string, languageId?: string}> $items An array of items with language information.
     * @param string[] $translatedFields An array of fields to be translated.
     *
     * @return array<int, array<string, array<string, string>>> An array of items with nested arrays containing translated values.
     *
     * @example
     *
     * ```php
     * $items = [['id' => 'fooId', 'name' => 'foo in EN', 'languageId' => 'en-GB'], ['id' => 'fooId', 'name' => 'foo in De', 'languageId' => 'de-DE'], ['id' => 'barId', 'name' => 'bar', 'description' => 'bar description', 'languageId' => 'en-GB']];
     * $esValue = ElasticsearchIndexingHelper::mapToManyAssociations($items, ['name', 'description']);
     * // [
     *      [
     *          'id' => 'fooId',
     *          'name' => [
     *              'en-GB' => 'foo in EN',
     *              'de-DE' => 'foo in De',
     *          ],
     *          'description' => [
     *              'en-GB' => null,
     *          ]
     *      ],
     *      [
     *          'id' => 'barId',
     *          'name' => ['en-GB' => 'bar'],
     *          'description' => [
     *              'en-GB' => 'bar description',
     *          ],
     *      ],
     * ]
     * ```
     */
    public static function toManyAssociations(array $items, array $translatedFields): array
    {
        // Group items by 'id'
        $groupedItems = [];

        foreach ($items as $item) {
            if (empty($item['languageId'])) {
                continue;
            }

            $itemId = $item['id'];
            if (!isset($groupedItems[$itemId])) {
                $groupedItems[$itemId] = [
                    'id' => $itemId,
                    '_count' => 1,
                ];
            }

            foreach ($translatedFields as $field) {
                if (!empty($item[$field])) {
                    $groupedItems[$itemId][$field][$item['languageId']] = $item[$field];
                } elseif (!isset($groupedItems[$itemId][$field])) {
                    $groupedItems[$itemId][$field][$item['languageId']] = null;
                }
            }
        }

        // Convert grouped items into a numerically indexed array
        return array_values($groupedItems);
    }

    /**
     * @description This method is used to format custom fields to the correct format
     *
     * @param array<string, mixed> $customFields array of raw custom fields from database keyed by language ID
     *
     * @throws \JsonException
     *
     * @return array<string, array<string, mixed>> array of formatted custom fields keyed by language ID
     */
    public function customFields(string $entity, array $customFields, Context $context): array
    {
        $mapped = [];

        foreach ($customFields as $languageId => $customField) {
            if (empty($customField)) {
                continue;
            }

            // MariaDB servers gives the result as string and not directly decoded
            if (\is_string($customField)) {
                $customField = json_decode($customField, true, 512, \JSON_THROW_ON_ERROR);
            }

            $mapped[$languageId] = $this->formatCustomField($entity, $customField ?: [], $context);
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $customFields
     *
     * @throws Exception
     *
     * @return array<string, mixed>
     */
    private function formatCustomField(string $entity, array $customFields, Context $context): array
    {
        $types = $this->indexingUtils->getCustomFieldTypes($entity, $context);

        foreach ($customFields as $name => $customField) {
            $type = $types[$name] ?? null;

            if ($type === null) {
                unset($customFields[$name]);

                continue;
            }
            
            /**
             * Do not convert/cast fields defined as text.
             * See https://issues.shopware.com/issues/NEXT-33271
             */
            if ($type === CustomFieldTypes::TEXT) {
                continue;
            }

            if ($type === CustomFieldTypes::BOOL) {
                $customFields[$name] = (bool) $customField;
            } elseif (\is_numeric($customField)) {
                $customFields[$name] = (float) $customField;
            }
        }

        return $customFields;
    }
}
