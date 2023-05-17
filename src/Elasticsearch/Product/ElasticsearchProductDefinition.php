<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
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
class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
{
    final public const KEYWORD_FIELD = [
        'type' => 'keyword',
        'normalizer' => 'sw_lowercase_normalizer',
    ];

    final public const BOOLEAN_FIELD = ['type' => 'boolean'];

    final public const FLOAT_FIELD = ['type' => 'double'];

    final public const INT_FIELD = ['type' => 'long'];

    private const SEARCH_FIELD = [
        'fields' => [
            'search' => ['type' => 'text'],
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

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
        return [
            '_source' => ['includes' => ['id', 'autoIncrement']],
            'properties' => [
                'id' => self::KEYWORD_FIELD,
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
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => self::KEYWORD_FIELD,
                        'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                        '_count' => self::INT_FIELD,
                    ],
                ],
                'childCount' => self::INT_FIELD,
                'autoIncrement' => self::INT_FIELD,
                'manufacturerNumber' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'description' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'metaTitle' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'metaDescription' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'displayGroup' => self::KEYWORD_FIELD,
                'ean' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'height' => self::FLOAT_FIELD,
                'length' => self::FLOAT_FIELD,
                'manufacturer' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => self::KEYWORD_FIELD,
                        'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                        '_count' => self::INT_FIELD,
                    ],
                ],
                'markAsTopseller' => self::BOOLEAN_FIELD,
                'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'options' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => self::KEYWORD_FIELD,
                        'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                        'groupId' => self::KEYWORD_FIELD,
                        '_count' => self::INT_FIELD,
                    ],
                ],
                'productNumber' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'properties' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => self::KEYWORD_FIELD,
                        'name' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                        'groupId' => self::KEYWORD_FIELD,
                        '_count' => self::INT_FIELD,
                    ],
                ],
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
                'customFields' => $this->getCustomFieldsMapping($context),
                'customSearchKeywords' => self::KEYWORD_FIELD + self::SEARCH_FIELD,
                'states' => self::KEYWORD_FIELD,
            ],
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
        ];
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        return $this->searchQueryBuilder->build($criteria, $context);
    }

    /**
     * @param array<string> $ids
     *
     * @return array<mixed>
     */
    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchProducts($ids, $context);

        $groupIds = [];
        foreach ($data as $row) {
            foreach (json_decode($row['propertyIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[(string) $id] = true;
            }
            foreach (json_decode($row['optionIds'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR) as $id) {
                $groupIds[(string) $id] = true;
            }
        }

        $groups = $this->fetchPropertyGroups(\array_keys($groupIds), $context);

        $documents = [];

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

            $translations = $this->filterToOne(json_decode((string) $item['translation'], true, 512, \JSON_THROW_ON_ERROR));
            $parentTranslations = $this->filterToOne(json_decode((string) $item['translation_parent'], true, 512, \JSON_THROW_ON_ERROR));
            $manufacturer = $this->filterToOne(json_decode((string) $item['manufacturer_translation'], true, 512, \JSON_THROW_ON_ERROR));
            $tags = $this->filterToOne(json_decode((string) $item['tags'], true, 512, \JSON_THROW_ON_ERROR), 'id');
            $categories = $this->filterToMany(json_decode((string) $item['categories'], true, 512, \JSON_THROW_ON_ERROR));

            $customFields = $this->takeItem('customFields', $context, $translations, $parentTranslations) ?? [];

            // MariaDB servers gives the result as string and not directly decoded
            // @codeCoverageIgnoreStart
            if (\is_string($customFields)) {
                $customFields = json_decode($customFields, true, 512, \JSON_THROW_ON_ERROR);
            }
            // @codeCoverageIgnoreEnd

            $document = [
                'id' => $id,
                'name' => $this->stripText($this->takeItem('name', $context, $translations, $parentTranslations) ?? ''),
                'description' => $this->stripText($this->takeItem('description', $context, $translations, $parentTranslations) ?? ''),
                'metaTitle' => $this->stripText($this->takeItem('metaTitle', $context, $translations, $parentTranslations) ?? ''),
                'metaDescription' => $this->stripText($this->takeItem('metaDescription', $context, $translations, $parentTranslations) ?? ''),
                'customSearchKeywords' => $this->takeItem('customSearchKeywords', $context, $translations, $parentTranslations) ?? '[]',
                'ratingAverage' => (float) $item['ratingAverage'],
                'active' => (bool) $item['active'],
                'available' => (bool) $item['available'],
                'isCloseout' => (bool) $item['isCloseout'],
                'shippingFree' => (bool) $item['shippingFree'],
                'markAsTopseller' => (bool) $item['markAsTopseller'],
                'customFields' => $this->formatCustomFields($customFields, $context),
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
                'manufacturer' => [
                    'id' => $item['productManufacturerId'],
                    'name' => $this->takeItem('name', $context, $manufacturer) ?? '',
                    '_count' => 1,
                ],
                'releaseDate' => isset($item['releaseDate']) ? (new \DateTime($item['releaseDate']))->format('c') : null,
                'createdAt' => isset($item['createdAt']) ? (new \DateTime($item['createdAt']))->format('c') : null,
                'optionIds' => $optionIds,
                'options' => array_values(array_map(fn (string $optionId) => ['id' => $optionId, 'name' => $groups[$optionId]['name'], 'groupId' => $groups[$optionId]['property_group_id'], '_count' => 1], $optionIds)),
                'categories' => array_values(array_map(fn ($category) => [
                    'id' => $category[Defaults::LANGUAGE_SYSTEM]['id'],
                    'name' => $this->takeItem('name', $context, $category) ?? '',
                ], $categories)),
                'categoriesRo' => array_values(array_map(fn (string $categoryId) => ['id' => $categoryId, '_count' => 1], $categoriesRo)),
                'properties' => array_values(array_map(fn (string $propertyId) => ['id' => $propertyId, 'name' => $groups[$propertyId]['name'], 'groupId' => $groups[$propertyId]['property_group_id'], '_count' => 1], $propertyIds)),
                'propertyIds' => $propertyIds,
                'taxId' => $item['taxId'],
                'tags' => array_values(array_map(fn (string $tagId) => ['id' => $tagId, 'name' => $tags[$tagId]['name'], '_count' => 1], $tagIds)),
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
                    'languageId', lower(hex(product_main.language_id)),
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
                    'languageId', lower(hex(product_parent.language_id)),
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
                    'languageId', lower(hex(product_manufacturer_translation.language_id)),
                    'name', product_manufacturer_translation.name
                )
            ),
        ']'
    ) as manufacturer_translation,

    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'id', lower(hex(tag.id)),
                    'name', tag.name
                )
            ),
        ']'
    ) as tags,

    CONCAT(
        '[',
        GROUP_CONCAT(DISTINCT
                JSON_OBJECT(
                    'id', lower(hex(category_translation.category_id)),
                    'languageId', lower(hex(category_translation.language_id)),
                    'name', category_translation.name
                )
            ),
        ']'
    ) as categories,

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
    LEFT JOIN product_translation product_main ON (product_main.product_id = p.id AND product_main.product_version_id = p.version_id AND product_main.language_id IN(:languageIds))
    LEFT JOIN product_translation product_parent ON (product_parent.product_id = p.parent_id AND product_parent.product_version_id = p.version_id AND product_parent.language_id IN(:languageIds))

    LEFT JOIN product_manufacturer_translation on (product_manufacturer_translation.product_manufacturer_id = IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id) AND product_manufacturer_translation.product_manufacturer_version_id = p.version_id AND product_manufacturer_translation.language_id IN(:languageIds))

    LEFT JOIN product_tag ON (product_tag.product_id = p.tags AND product_tag.product_version_id = p.version_id)
    LEFT JOIN tag ON (tag.id = product_tag.tag_id)

    LEFT JOIN product_category ON (product_category.product_id = p.categories AND product_category.product_version_id = p.version_id)
    LEFT JOIN category_translation ON (category_translation.category_id = product_category.category_id AND category_translation.category_version_id = product_category.category_version_id AND category_translation.language_id IN(:languageIds))

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL OR JSON_EXTRACT(`p`.`variant_listing_config`, "$.displayParent") = 1)

GROUP BY p.id
SQL;

        $data = $this->connection->fetchAllAssociative(
            $sql,
            [
                'ids' => $ids,
                'languageIds' => Uuid::fromHexToBytesList($context->getLanguageIdChain()),
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::STRING,
                'languageIds' => ArrayParameterType::STRING,
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
     * @param array<string> $propertyIds
     *
     * @return array<string, array{id: string, name: string, property_group_id: string, translations: string}>
     */
    private function fetchPropertyGroups(array $propertyIds, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
       LOWER(HEX(id)) as id,
       LOWER(HEX(property_group_id)) as property_group_id,
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
                   ON (property_group_option_translation.property_group_option_id = property_group_option.id AND
                       property_group_option_translation.language_id IN (:languageIds))

WHERE property_group_option.id in (:ids)
GROUP BY property_group_option.id
SQL;

        /** @var array<string, array{id: string, property_group_id: string, translations: string}> $options */
        $options = $this->connection->fetchAllAssociativeIndexed(
            $sql,
            [
                'ids' => Uuid::fromHexToBytesList($propertyIds),
                'languageIds' => Uuid::fromHexToBytesList($context->getLanguageIdChain()),
            ],
            [
                'ids' => ArrayParameterType::STRING,
                'languageIds' => ArrayParameterType::STRING,
            ]
        );

        foreach ($options as $optionId => $option) {
            $translation = $this->filterToOne(json_decode($option['translations'], true, 512, \JSON_THROW_ON_ERROR));

            $options[(string) $optionId]['name'] = (string) $this->takeItem('name', $context, $translation);
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
     * @param array<mixed> $items
     *
     * @return mixed|null
     */
    private function takeItem(string $key, Context $context, ...$items)
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            foreach ($items as $item) {
                if (isset($item[$languageId][$key])) {
                    return $item[$languageId][$key];
                }
            }
        }

        return null;
    }

    /**
     * @param array<mixed>[] $items
     *
     * @return array<int|string, mixed>
     */
    private function filterToOne(array $items, string $key = 'languageId'): array
    {
        $filtered = [];

        foreach ($items as $item) {
            // Empty row
            if ($item[$key] === null) {
                continue;
            }

            $filtered[$item[$key]] = $item;
        }

        return $filtered;
    }

    /**
     * @param array<mixed> $items
     *
     * @return array<mixed>
     */
    private function filterToMany(array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if ($item['id'] === null) {
                continue;
            }

            if (!isset($filtered[$item['id']])) {
                $filtered[$item['id']] = [];
            }

            $filtered[$item['id']][$item['languageId']] = $item;
        }

        return $filtered;
    }
}
