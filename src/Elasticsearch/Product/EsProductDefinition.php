<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Product\Event\ElasticsearchProductCustomFieldsMappingEvent;

#[Package('core')]
class EsProductDefinition extends AbstractElasticsearchDefinition
{
    /**
     * @var array<string, string>|null
     */
    private ?array $customFieldsTypes = null;

    /**
     * @internal
     *
     * @param array<string, string> $customMapping
     */
    public function __construct(
        protected ProductDefinition $definition,
        private readonly Connection $connection,
        private array $customMapping,
        protected EventDispatcherInterface $eventDispatcher,
        private readonly AbstractProductSearchQueryBuilder $searchQueryBuilder
    ) {
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    /**
     * @return array{_source: array{includes: string[]}, properties: array<mixed>}
     */
    public function getMapping(Context $context): array
    {
        $languageIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM language');

        $properties = [
            'id' => self::KEYWORD_FIELD,
            'name' => [
                'properties' => [],
            ],
            'description' => [
                'properties' => [],
            ],
            'metaTitle' => [
                'properties' => [],
            ],
            'metaDescription' => [
                'properties' => [],
            ],
            'customSearchKeywords' => [
                'properties' => [],
            ],
            'customFields' => [
                'properties' => [],
            ],
            'categories' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    '_count' => self::INT_FIELD,
                    'name' => [
                        'properties' => [],
                    ],
                ],
            ],
            'manufacturer' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    '_count' => self::INT_FIELD,
                    'name' => [
                        'properties' => [],
                    ],
                ],
            ],
            'options' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    'groupId' => self::KEYWORD_FIELD,
                    '_count' => self::INT_FIELD,
                    'name' => [
                        'properties' => [],
                    ],
                ],
            ],
            'properties' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    'groupId' => self::KEYWORD_FIELD,
                    '_count' => self::INT_FIELD,
                    'name' => [
                        'properties' => [],
                    ],
                ],
            ],
            'parentId' => self::KEYWORD_FIELD,
            'active' => self::BOOLEAN_FIELD,
            'available' => self::BOOLEAN_FIELD,
            'isCloseout' => self::BOOLEAN_FIELD,
            'categoriesRo' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    '_count' => self::INT_FIELD,
                ],
            ],
            'childCount' => self::INT_FIELD,
            'autoIncrement' => self::INT_FIELD,
            'manufacturerId' => self::KEYWORD_FIELD,
            'manufacturerNumber' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
            'displayGroup' => self::KEYWORD_FIELD,
            'ean' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
            'height' => self::FLOAT_FIELD,
            'length' => self::FLOAT_FIELD,
            'markAsTopseller' => self::BOOLEAN_FIELD,
            'productNumber' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
            'ratingAverage' => self::FLOAT_FIELD,
            'releaseDate' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
            'createdAt' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
            'sales' => self::INT_FIELD,
            'stock' => self::INT_FIELD,
            'availableStock' => self::INT_FIELD,
            'shippingFree' => self::BOOLEAN_FIELD,
            'taxId' => self::KEYWORD_FIELD,
            'tags' => [
                'type' => 'nested',
                'properties' => [
                    'id' => self::KEYWORD_FIELD,
                    'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                    '_count' => self::INT_FIELD,
                ],
            ],
            'visibilities' => [
                'type' => 'nested',
                'properties' => [
                    'salesChannelId' => self::KEYWORD_FIELD,
                    'visibility' => self::INT_FIELD,
                    '_count' => self::INT_FIELD,
                ],
            ],
            'coverId' => self::KEYWORD_FIELD,
            'weight' => self::FLOAT_FIELD,
            'width' => self::FLOAT_FIELD,
            'states' => self::KEYWORD_FIELD,
        ];

        foreach ($languageIds as $languageId) {
            $properties['categories']['properties']['name']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['manufacturer']['properties']['name']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['properties']['properties']['name']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['options']['properties']['name']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['name']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['description']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['metaTitle']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['metaDescription']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['customSearchKeywords']['properties'][$languageId] = self::KEYWORD_FIELD + self::SEARCH_FIELD;
            $properties['customFields']['properties'][$languageId] = $this->getCustomFieldsMapping($context);
        }

        return [
            '_source' => ['includes' => ['id', 'autoIncrement']],
            'dynamic_templates' => [
                [
                    'cheapest_price' => [
                        'match' => 'cheapest_price_rule*',
                        'mapping' => ['type' => 'double'],
                    ],
                ],
                [
                    'price_percentage' => [
                        'path_match' => 'price.*.percentage.*',
                        'mapping' => ['type' => 'double'],
                    ],
                ],
                [
                    'long_to_double' => [
                        'match_mapping_type' => 'long',
                        'mapping' => ['type' => 'double'],
                    ],
                ],
            ],
            'properties' => $properties,
        ];
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        return $this->searchQueryBuilder->build($criteria, $context);
    }

    /**
     * @inheritDoc
     */
    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchProducts($ids, $context);
        $documents = [];

        $groupIds = [];

        foreach ($data as $row) {
            foreach (json_decode($row['propertyIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[(string) $id] = true;
            }
            foreach (json_decode($row['optionIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[(string) $id] = true;
            }
        }

        $groups = $this->fetchPropertyGroups(\array_keys($groupIds));

        foreach ($data as $id => $item) {
            $visibilities = array_values(array_unique(array_filter(explode('|', $item['visibilities'] ?? ''))));

            $visibilities = array_map(static function (string $text) {
                [$visibility, $salesChannelId] = explode(',', $text);

                return [
                    'visibility' => $visibility,
                    'salesChannelId' => $salesChannelId,
                    '_count' => 1,
                ];
            }, $visibilities);

            $optionIds = json_decode($item['optionIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $propertyIds = json_decode($item['propertyIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $tagIds = json_decode($item['tagIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $categoriesRo = json_decode($item['categoryIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $states = json_decode($item['states'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $tags = json_decode((string) $item['tags'], true, 512, \JSON_THROW_ON_ERROR);
            $categories = json_decode((string) $item['categories'], true, 512, \JSON_THROW_ON_ERROR);
            $manufacturers = json_decode((string) $item['manufacturer_translation'], true, 512, \JSON_THROW_ON_ERROR);
            $translations = json_decode((string) $item['translation'], true, 512, \JSON_THROW_ON_ERROR);
            $parentTranslations = json_decode((string) $item['translation_parent'], true, 512, \JSON_THROW_ON_ERROR);

            $filteredTags = [];

            foreach ($tags as $tag) {
                if (empty($tag['id'])) {
                    continue;
                }

                $filteredTags[] = [
                    'id' => $tag['id'],
                    'name' => $this->stripText($tag['name'] ?? ''),
                    '_count' => 1,
                ];
            }

            $document = [
                'id' => $id,
                'ratingAverage' => (float) $item['ratingAverage'],
                'active' => (bool) $item['active'],
                'available' => (bool) $item['available'],
                'isCloseout' => (bool) $item['isCloseout'],
                'shippingFree' => (bool) $item['shippingFree'],
                'markAsTopseller' => (bool) $item['markAsTopseller'],
                'visibilities' => $visibilities,
                'availableStock' => (int) $item['availableStock'],
                'productNumber' => $item['productNumber'],
                'ean' => $item['ean'],
                'displayGroup' => $item['displayGroup'],
                'sales' => (int) $item['sales'],
                'stock' => (int) $item['stock'],
                'weight' => (float) $item['weight'],
                'width' => (float) $item['width'],
                'length' => (float) $item['length'],
                'height' => (float) $item['height'],
                'manufacturerId' => $item['productManufacturerId'],
                'manufacturerNumber' => $item['manufacturerNumber'],
                'releaseDate' => isset($item['releaseDate']) ? (new \DateTime($item['releaseDate']))->format('c') : null,
                'createdAt' => isset($item['createdAt']) ? (new \DateTime($item['createdAt']))->format('c') : null,
                'categoriesRo' => array_values(array_map(fn (string $categoryId) => ['id' => $categoryId, '_count' => 1], $categoriesRo)),
                'optionIds' => $optionIds,
                'propertyIds' => $propertyIds,
                'taxId' => $item['taxId'],
                'tags' => $filteredTags,
                'tagIds' => $tagIds,
                'parentId' => $item['parentId'],
                'coverId' => $item['coverId'],
                'childCount' => (int) $item['childCount'],
                'states' => $states,
            ];

            if ($item['cheapest_price_accessor']) {
                $cheapestPriceAccessor = json_decode((string) $item['cheapest_price_accessor'], true, 512, \JSON_THROW_ON_ERROR);

                foreach ($cheapestPriceAccessor as $rule => $cheapestPriceCurrencies) {
                    foreach ($cheapestPriceCurrencies as $currency => $taxes) {
                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross';
                        $document[$key] = $taxes['gross'];

                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_net';
                        $document[$key] = $taxes['net'];

                        if (empty($taxes['percentage'])) {
                            continue;
                        }

                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross_percentage';
                        $document[$key] = $taxes['percentage']['gross'];

                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_net_percentage';
                        $document[$key] = $taxes['percentage']['net'];
                    }
                }
            }

            $document['name'] = $this->mapTranslatedField('name', true, ...$translations, ...$parentTranslations);
            $document['description'] = $this->mapTranslatedField('description', true, ...$translations, ...$parentTranslations);
            $document['metaTitle'] = $this->mapTranslatedField('metaTitle', true, ...$translations, ...$parentTranslations);
            $document['metaDescription'] = $this->mapTranslatedField('metaDescription', true, ...$translations, ...$parentTranslations);
            $document['customSearchKeywords'] = $this->mapTranslatedField('customSearchKeywords', false, ...$translations, ...$parentTranslations);
            $document['customFields'] = [];
            $customFields = $this->mapTranslatedField('customFields', false, ...$translations, ...$parentTranslations);

            foreach ($customFields as $languageId => $customField) {
                if (empty($customField)) {
                    continue;
                }

                // MariaDB servers gives the result as string and not directly decoded
                // @codeCoverageIgnoreStart
                if (\is_string($customField)) {
                    $customField = json_decode($customField, true, 512, \JSON_THROW_ON_ERROR);
                }
                // @codeCoverageIgnoreEnd

                $document['customFields'][$languageId] = $this->formatCustomFields($customField ?: [], $context);
            }

            $document['properties'] = array_values(array_map(function (string $propertyId) use ($groups) {
                return array_merge([
                    'id' => $propertyId,
                    '_count' => 1,
                ], $groups[$propertyId]);
            }, $propertyIds));

            $document['manufacturer'] = [
                'id' => $item['productManufacturerId'],
                '_count' => 1,
            ];

            foreach ($manufacturers as $manufacturer) {
                if (empty($manufacturer['languageId'])) {
                    continue;
                }

                $document['manufacturer']['name'][$manufacturer['languageId']] = $this->stripText($manufacturer['name'] ?? '');
            }

            $document['categories'] = [];
            foreach ($categories as $category) {
                if (empty($category['languageId'])) {
                    continue;
                }

                $document['categories'][$category['id']] = $document['categories'][$category['id']] ?? [
                    'id' => $category['id'],
                    '_count' => 1,
                ];

                $document['categories'][$category['id']]['name'][$category['languageId']] = $this->stripText($category['name'] ?? '');
            }

            $document['categories'] = array_values($document['categories']);

            $document['options'] = array_values(array_map(function (string $optionId) use ($groups) {
                return array_merge([
                    'id' => $optionId,
                    '_count' => 1,
                ], $groups[$optionId]);
            }, $optionIds));

            $documents[$id] = $document;
        }

        return $documents;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<mixed>
     */
    private function fetchProducts(array $ids, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(p.active, pp.active) AS active,
    p.available AS available,

    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'id', LOWER(HEX(tag.id)),
                    'name', tag.name
                )
            ),
        ']'
    ) as tags,

    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'languageId', LOWER(HEX(product_main.language_id)),
                    'name', product_main.name,
                    'description', product_main.description,
                    'metaTitle', product_main.meta_title,
                    'metaDescription', product_main.meta_description,
                    'customSearchKeywords', product_main.custom_search_keywords,
                    'customFields', product_main.custom_fields
                )
            ),
        ']'
    ) as translation,
    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'languageId', LOWER(HEX(product_parent.language_id)),
                    'name', product_parent.name,
                    'description', product_parent.description,
                    'metaTitle', product_parent.meta_title,
                    'metaDescription', product_parent.meta_description,
                    'customSearchKeywords', product_parent.custom_search_keywords,
                    'customFields', product_parent.custom_fields
                )
            ),
        ']'
    ) as translation_parent,
    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'id', LOWER(HEX(category_translation.category_id)),
                    'languageId', LOWER(HEX(category_translation.language_id)),
                    'name', category_translation.name
                )
            ),
        ']'
    ) as categories,

    CONCAT(
        '[',
            GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'name', product_manufacturer_translation.name,
                    'languageId', LOWER(HEX(product_manufacturer_translation.language_id))
                )
            ),
        ']'
    ) as manufacturer_translation,

    IFNULL(p.manufacturer_number, pp.manufacturer_number) AS manufacturerNumber,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    p.product_number as productNumber,
    p.sales,
    LOWER(HEX(IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id))) AS productManufacturerId,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    LOWER(HEX(IFNULL(p.product_media_id, pp.product_media_id))) AS coverId,
    IFNULL(p.weight, pp.weight) AS weight,
    IFNULL(p.length, pp.length) AS length,
    IFNULL(p.height, pp.height) AS height,
    IFNULL(p.width, pp.width) AS width,
    IFNULL(p.release_date, pp.release_date) AS releaseDate,
    IFNULL(p.created_at, pp.created_at) AS createdAt,
    IFNULL(p.category_tree, pp.category_tree) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.tag_ids, pp.tag_ids) AS tagIds,
    LOWER(HEX(IFNULL(p.tax_id, pp.tax_id))) AS taxId,
    IFNULL(p.stock, pp.stock) AS stock,
    IFNULL(p.ean, pp.ean) AS ean,
    IFNULL(p.mark_as_topseller, pp.mark_as_topseller) AS markAsTopseller,
    p.auto_increment as autoIncrement,
    GROUP_CONCAT(CONCAT(product_visibility.visibility, ',', LOWER(HEX(product_visibility.sales_channel_id))) SEPARATOR '|') AS visibilities,
    p.display_group as displayGroup,
    IFNULL(p.cheapest_price_accessor, pp.cheapest_price_accessor) as cheapest_price_accessor,
    LOWER(HEX(p.parent_id)) as parentId,
    p.child_count as childCount,
    p.states

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = p.version_id)
    LEFT JOIN product_translation product_main ON product_main.product_id = p.id AND product_main.product_version_id = p.version_id
    LEFT JOIN product_translation product_parent ON product_parent.product_id = p.parent_id AND product_parent.product_version_id = p.version_id

    LEFT JOIN product_manufacturer_translation ON product_manufacturer_translation.product_manufacturer_id = IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id) AND product_manufacturer_translation.product_manufacturer_version_id = p.version_id

    LEFT JOIN product_tag ON (product_tag.product_id = p.tags AND product_tag.product_version_id = p.version_id)
    LEFT JOIN tag ON (tag.id = product_tag.tag_id)

    LEFT JOIN product_category ON (product_category.product_id = p.categories AND product_category.product_version_id = p.version_id)
    LEFT JOIN category_translation ON category_translation.category_id = product_category.category_id AND category_translation.category_version_id = product_category.category_version_id

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL OR JSON_EXTRACT(`p`.`variant_listing_config`, "$.displayParent") = 1)

GROUP BY p.id
SQL;

        $data = $this->connection->fetchAllAssociative(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ]
        );

        return FetchModeHelper::groupUnique($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomFieldsMapping(Context $context): array
    {
        $fieldMapping = $this->getCustomFieldTypes($context);
        $mapping = [
            'type' => 'object',
            'dynamic' => true,
            'properties' => [],
        ];

        foreach ($fieldMapping as $name => $type) {
            /** @var array<mixed> $esType */
            $esType = CustomFieldUpdater::getTypeFromCustomFieldType($type);

            $mapping['properties'][$name] = $esType;
        }

        return $mapping;
    }

    /**
     * @param array<string, mixed> $customFields
     *
     * @return array<string, mixed>
     */
    private function formatCustomFields(array $customFields, Context $context): array
    {
        $types = $this->getCustomFieldTypes($context);

        foreach ($customFields as $name => $customField) {
            $type = $types[$name] ?? null;

            if ($type === null) {
                unset($customFields[$name]);

                continue;
            }

            if ($type === CustomFieldTypes::BOOL) {
                $customFields[$name] = (bool) $customField;
            } elseif (\is_int($customField)) {
                $customFields[$name] = (float) $customField;
            }
        }

        return $customFields;
    }

    /**
     * @param list<string> $propertyIds
     *
     * @return array<string, array{id: string, name: string, groupId: string, name: array<string, string>}>
     */
    private function fetchPropertyGroups(array $propertyIds): array
    {
        $sql = <<<'SQL'
SELECT
       LOWER(HEX(id)) as id,
       LOWER(HEX(property_group_id)) as groupId,
       CONCAT(
               '[',
               GROUP_CONCAT(
                       JSON_OBJECT(
                               'languageId', lower(hex(property_group_option_translation.language_id)),
                               'name', property_group_option_translation.name
                           )
                   ),
               ']'
           ) as translations
FROM property_group_option
         LEFT JOIN property_group_option_translation
            ON property_group_option_translation.property_group_option_id = property_group_option.id

WHERE property_group_option.id in (:ids)
GROUP BY property_group_option.id
SQL;

        /** @var array<string, array{id: string, groupId: string, translations: string}> $options */
        $options = $this->connection->fetchAllAssociativeIndexed(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($propertyIds),
            ],
            [
                'ids' => ArrayParameterType::STRING,
            ]
        );

        foreach ($options as $optionId => $option) {
            $translation = json_decode($option['translations'] ?? '', true, 512, \JSON_THROW_ON_ERROR);

            $options[$optionId]['name'] = $option['name'] ?? [];
            foreach ($translation as $item) {
                $options[$optionId]['name'][$item['languageId']] = $this->stripText($item['name'] ?? '');
            }

            unset($options[$optionId]['translations']);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function getCustomFieldTypes(Context $context): array
    {
        if ($this->customFieldsTypes !== null) {
            return $this->customFieldsTypes;
        }

        $event = new ElasticsearchProductCustomFieldsMappingEvent($this->customMapping, $context);
        $this->eventDispatcher->dispatch($event);

        $this->customFieldsTypes = $event->getMappings();

        return $this->customFieldsTypes;
    }

    /**
     * @param array<int, array<string, string>> $items
     *
     * @return array<int|string, mixed>
     */
    private function mapTranslatedField(string $field, bool $stripText = true, ...$items): array
    {
        $value = [];

        foreach ($items as $item) {
            if (isset($item['languageId'])) {
                $value[$item['languageId']] = $stripText ? $this->stripText($item[$field] ?? '') : ($item[$field] ?? '');
            }
        }

        return $value;
    }
}
