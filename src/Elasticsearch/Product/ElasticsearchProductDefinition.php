<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SqlHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;

/**
 * @internal
 */
#[Package('core')]
class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $definition,
        private readonly Connection $connection,
        private readonly AbstractProductSearchQueryBuilder $searchQueryBuilder,
        private readonly ElasticsearchFieldBuilder $fieldBuilder,
        private readonly ElasticsearchFieldMapper $fieldMapper,
        private readonly SalesChannelLanguageLoader $languageLoader,
        private readonly bool $excludeSource,
        private readonly string $environment
    ) {
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getMapping(Context $context): array
    {
        $languageFields = $this->fieldBuilder->translated(self::getTextFieldConfig());

        $debug = $this->environment !== 'prod';

        $properties = [
            'id' => self::KEYWORD_FIELD,
            'name' => $languageFields,
            'description' => $languageFields,
            'metaTitle' => $languageFields,
            'metaDescription' => $languageFields,
            'customSearchKeywords' => $languageFields,
            'categories' => ElasticsearchFieldBuilder::nested([
                'name' => $languageFields,
            ]),
            'manufacturer' => ElasticsearchFieldBuilder::nested([
                'name' => $languageFields,
            ]),
            'options' => ElasticsearchFieldBuilder::nested([
                'groupId' => self::KEYWORD_FIELD,
                'name' => $languageFields,
            ]),
            'properties' => ElasticsearchFieldBuilder::nested([
                'groupId' => self::KEYWORD_FIELD,
                'name' => $languageFields,
                'group' => ElasticsearchFieldBuilder::nested(),
            ]),
            'parentId' => self::KEYWORD_FIELD,
            'active' => self::BOOLEAN_FIELD,
            'available' => self::BOOLEAN_FIELD,
            'isCloseout' => self::BOOLEAN_FIELD,
            'categoriesRo' => ElasticsearchFieldBuilder::nested(),
            'childCount' => self::INT_FIELD,
            'categoryTree' => self::KEYWORD_FIELD,
            'categoryIds' => self::KEYWORD_FIELD,
            'propertyIds' => self::KEYWORD_FIELD,
            'optionIds' => self::KEYWORD_FIELD,
            'tagIds' => self::KEYWORD_FIELD,
            'autoIncrement' => self::INT_FIELD,
            'manufacturerId' => self::KEYWORD_FIELD,
            'manufacturerNumber' => self::getTextFieldConfig(),
            'displayGroup' => self::KEYWORD_FIELD,
            'ean' => self::getTextFieldConfig(),
            'height' => self::FLOAT_FIELD,
            'length' => self::FLOAT_FIELD,
            'markAsTopseller' => self::BOOLEAN_FIELD,
            'productNumber' => self::getTextFieldConfig(),
            'ratingAverage' => self::FLOAT_FIELD,
            'releaseDate' => ElasticsearchFieldBuilder::datetime(),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'sales' => self::INT_FIELD,
            'stock' => self::INT_FIELD,
            'availableStock' => self::INT_FIELD,
            'shippingFree' => self::BOOLEAN_FIELD,
            'taxId' => self::KEYWORD_FIELD,
            'tags' => ElasticsearchFieldBuilder::nested(['name' => self::getTextFieldConfig()]),
            'visibilities' => ElasticsearchFieldBuilder::nested([
                'id' => null,
                'salesChannelId' => self::KEYWORD_FIELD,
                'visibility' => self::INT_FIELD,
            ]),
            'coverId' => self::KEYWORD_FIELD,
            'weight' => self::FLOAT_FIELD,
            'width' => self::FLOAT_FIELD,
            'states' => self::KEYWORD_FIELD,
            'customFields' => $this->fieldBuilder->customFields($this->getEntityDefinition()->getEntityName(), $context),
        ];

        $mapping = [
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

        if (!$this->excludeSource && !$debug) {
            $mapping['_source'] = ['includes' => ['id', 'autoIncrement']];
        }

        return $mapping;
    }

    /**
     * {@inheritdoc}
     */
    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        return $this->searchQueryBuilder->build($criteria, $context);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \JsonException
     */
    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchProducts($ids, $context);

        if (empty($data)) {
            return [];
        }

        $documents = [];

        $groupIds = [];

        /** @var array<string, string> $row */
        foreach ($data as $row) {
            foreach (ElasticsearchIndexingUtils::parseJson($row, 'propertyIds') as $id) {
                $groupIds[(string) $id] = true;
            }
            foreach (ElasticsearchIndexingUtils::parseJson($row, 'optionIds') as $id) {
                $groupIds[(string) $id] = true;
            }
        }

        $groups = $this->fetchProperties(\array_keys($groupIds));

        /** @var array<string, string> $item */
        foreach ($data as $id => $item) {
            /** @var array<int|string, array<string, string|null>> $translation */
            $translation = $item['translation'] ?? [];
            /** @var array<int, array{id: string, languageId?: string}> $categories */
            $categories = $item['categories'] ?? [];

            $documents[$id] = [
                'id' => $id,
                'autoIncrement' => (float) $item['autoIncrement'],
                'ratingAverage' => (float) $item['ratingAverage'],
                'active' => (bool) $item['active'],
                'available' => (bool) $item['available'],
                'isCloseout' => (bool) $item['isCloseout'],
                'shippingFree' => (bool) $item['shippingFree'],
                'markAsTopseller' => (bool) $item['markAsTopseller'],
                'visibilities' => array_map(function (array $visibility) {
                    return array_merge([
                        '_count' => 1,
                    ], $visibility);
                }, ElasticsearchIndexingUtils::parseJson($item, 'visibilities')),
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
                'categoryTree' => ElasticsearchIndexingUtils::parseJson($item, 'categoryTree'),
                'categoriesRo' => array_values(array_map(fn (string $categoryId) => ['id' => $categoryId, '_count' => 1], ElasticsearchIndexingUtils::parseJson($item, 'categoryTree'))),
                'taxId' => $item['taxId'],
                'tags' => array_filter(array_map(function (array $tag) {
                    return empty($tag['id']) ? null : [
                        'id' => $tag['id'],
                        'name' => ElasticsearchIndexingUtils::stripText($tag['name'] ?? ''),
                        '_count' => 1,
                    ];
                }, ElasticsearchIndexingUtils::parseJson($item, 'tags'))),
                'parentId' => $item['parentId'],
                'coverId' => $item['coverId'],
                'childCount' => (int) $item['childCount'],
                'categories' => ElasticsearchFieldMapper::toManyAssociations(items: $categories ?? [], translatedFields: ['name']),
                'manufacturer' => [
                    'id' => $item['productManufacturerId'],
                    'name' => ElasticsearchFieldMapper::translated(field: 'manufacturerName', items: $translation),
                    '_count' => 1,
                ],
                'properties' => array_values(array_map(function (string $propertyId) use ($groups) {
                    return array_merge([
                        'id' => $propertyId,
                        '_count' => 1,
                    ], $groups[$propertyId] ?? []);
                }, ElasticsearchIndexingUtils::parseJson($item, 'propertyIds'))),
                'options' => array_values(array_map(function (string $optionId) use ($groups) {
                    return array_merge([
                        'id' => $optionId,
                        '_count' => 1,
                    ], $groups[$optionId] ?? []);
                }, ElasticsearchIndexingUtils::parseJson($item, 'optionIds'))),
                'categoryIds' => ElasticsearchIndexingUtils::parseJson($item, 'categoryIds'),
                'optionIds' => ElasticsearchIndexingUtils::parseJson($item, 'optionIds'),
                'propertyIds' => ElasticsearchIndexingUtils::parseJson($item, 'propertyIds'),
                'tagIds' => ElasticsearchIndexingUtils::parseJson($item, 'tagIds'),
                'states' => ElasticsearchIndexingUtils::parseJson($item, 'states'),
                'customFields' => $this->mapCustomFields(
                    variantCustomFields: ElasticsearchFieldMapper::translated(field: 'customFields', items: $translation, stripText: false),
                    parentCustomFields: ElasticsearchFieldMapper::translated(field: 'parentCustomFields', items: $translation, stripText: false),
                    context: $context
                ),
                'name' => ElasticsearchFieldMapper::translated(field: 'name', items: $translation),
                'description' => ElasticsearchFieldMapper::translated(field: 'description', items: $translation),
                'metaTitle' => ElasticsearchFieldMapper::translated(field: 'metaTitle', items: $translation),
                'metaDescription' => ElasticsearchFieldMapper::translated(field: 'metaDescription', items: $translation),
                'customSearchKeywords' => ElasticsearchFieldMapper::translated(field: 'customSearchKeywords', items: $translation),
                ...$this->mapCheapestPrice(ElasticsearchIndexingUtils::parseJson($item, 'cheapest_price_accessor')),
            ];
        }

        return $documents;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, array<mixed>|string>>
     */
    private function fetchProducts(array $ids, Context $context): array
    {
        $languages = \array_keys($this->languageLoader->loadLanguages());

        $baseSql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(p.active, pp.active) AS active,
    p.available AS available,
    #tags#,
    #visibilities#,
    IFNULL(p.manufacturer_number, pp.manufacturer_number) AS manufacturerNumber,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    p.product_number as productNumber,
    p.sales,
    LOWER(HEX(p.manufacturer)) AS productManufacturerId,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    LOWER(HEX(IFNULL(p.product_media_id, pp.product_media_id))) AS coverId,
    IFNULL(p.weight, pp.weight) AS weight,
    IFNULL(p.length, pp.length) AS length,
    IFNULL(p.height, pp.height) AS height,
    IFNULL(p.width, pp.width) AS width,
    IFNULL(p.release_date, pp.release_date) AS releaseDate,
    IFNULL(p.created_at, pp.created_at) AS createdAt,
    IFNULL(p.category_tree, pp.category_tree) AS categoryTree,
    IFNULL(p.category_ids, pp.category_ids) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.tag_ids, pp.tag_ids) AS tagIds,
    LOWER(HEX(IFNULL(p.tax_id, pp.tax_id))) AS taxId,
    IFNULL(p.stock, pp.stock) AS stock,
    IFNULL(p.ean, pp.ean) AS ean,
    IFNULL(p.mark_as_topseller, pp.mark_as_topseller) AS markAsTopseller,
    p.auto_increment as autoIncrement,
    p.display_group as displayGroup,
    IFNULL(p.cheapest_price_accessor, pp.cheapest_price_accessor) as cheapest_price_accessor,
    LOWER(HEX(p.parent_id)) as parentId,
    p.child_count as childCount,
    p.states

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = p.version_id)
    LEFT JOIN product_tag ON (product_tag.product_id = p.tags AND product_tag.product_version_id = p.version_id)
    LEFT JOIN tag ON tag.id = product_tag.tag_id

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL OR JSON_EXTRACT(`p`.`variant_listing_config`, "$.displayParent") = 1)

GROUP BY p.id
SQL;

        $baseMapping = [
            '#tags#' => SqlHelper::objectArray([
                'name' => 'tag.name',
                'id' => 'LOWER(HEX(tag.id))',
            ], 'tags'),
            '#visibilities#' => SqlHelper::objectArray([
                'visibility' => 'product_visibility.visibility',
                'salesChannelId' => 'LOWER(HEX(product_visibility.sales_channel_id))',
            ], 'visibilities'),
        ];

        /** @var array<string, array<string, string>> $base */
        $base = $this->connection->fetchAllAssociativeIndexed(
            str_replace(array_keys($baseMapping), array_values($baseMapping), $baseSql),
            [
                'ids' => $ids,
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $validProductIds = array_keys($base);

        $translationSql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(product_main.name, product_parent.name) AS name,
    IFNULL(product_main.description, product_parent.description) AS description,
    IFNULL(product_main.meta_title, product_parent.meta_title) AS metaTitle,
    IFNULL(product_main.meta_description, product_parent.meta_description) AS metaDescription,
    product_main.custom_fields AS customFields,
    product_parent.custom_fields AS parentCustomFields,
    IFNULL(product_main.custom_search_keywords, product_parent.custom_search_keywords) AS customSearchKeywords,
    manufacturer.name AS manufacturerName,
    #categories#
FROM product p
    LEFT JOIN product_translation product_main ON product_main.product_id = p.id AND product_main.product_version_id = p.version_id AND product_main.language_id = :languageId
    LEFT JOIN product_translation product_parent ON product_parent.product_id = p.parent_id AND product_parent.product_version_id = p.version_id AND product_parent.language_id = :languageId
    LEFT JOIN product_manufacturer_translation manufacturer ON manufacturer.product_manufacturer_id = p.manufacturer AND manufacturer.language_id = :languageId AND manufacturer.product_manufacturer_version_id = p.version_id AND manufacturer.name IS NOT NULL
    LEFT JOIN product_category ON (product_category.product_id = p.categories AND product_category.product_version_id = p.version_id)
    LEFT JOIN category_translation category ON category.category_id = product_category.category_id AND category.category_version_id = product_category.category_version_id AND category.name IS NOT NULL AND category.language_id = :languageId

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId

GROUP BY p.id
SQL;

        $translationMapping = [
            '#categories#' => SqlHelper::objectArray([
                'languageId' => 'LOWER(HEX(category.language_id))',
                'id' => 'LOWER(HEX(category.category_id))',
                'name' => 'category.name',
            ], 'categories'),
        ];

        /** @var string $translationSql */
        $translationSql = str_replace(array_keys($translationMapping), array_values($translationMapping), $translationSql);

        foreach ($languages as $languageId) {
            /** @var array<string, array<string, string>> $translations */
            $translations = $this->connection->fetchAllAssociativeIndexed(
                $translationSql,
                [
                    'ids' => Uuid::fromHexToBytesList($validProductIds),
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
                ],
                [
                    'ids' => ArrayParameterType::BINARY,
                ]
            );

            foreach ($translations as $id => $translation) {
                $translation['languageId'] = $languageId;
                /** @var array<mixed> $categories */
                $categories = $base[$id]['categories'] ?? [];
                $translatedCategories = ElasticsearchIndexingUtils::parseJson($translation, 'categories');

                if (!empty($translation['customSearchKeywords'])) {
                    $translation['customSearchKeywords'] = ElasticsearchIndexingUtils::parseJson($translation, 'customSearchKeywords');
                }

                $base[$id]['translation'] ??= [];
                \assert(\is_array($base[$id]['translation']));
                $base[$id]['translation'][] = $translation;
                $base[$id]['categories'] = [...$categories, ...$translatedCategories];
            }
        }

        return $base;
    }

    /**
     * @param list<string> $propertyIds
     *
     * @return array<string, array{id: string, groupId: string, group: array<string, string|int>, translations?: string, name: array<string, string|null>}>
     */
    private function fetchProperties(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [];
        }

        $sql = <<<'SQL'
SELECT
       LOWER(HEX(id)) as id,
       LOWER(HEX(property_group_id)) as groupId,
       #translations#
FROM property_group_option
         LEFT JOIN property_group_option_translation
            ON property_group_option_translation.property_group_option_id = property_group_option.id

WHERE property_group_option.id in (:ids)
GROUP BY property_group_option.id
SQL;

        /** @var array<string, array{id: string, groupId: string, translations: string}> $options */
        $options = $this->connection->fetchAllAssociativeIndexed(
            str_replace(
                '#translations#',
                SqlHelper::objectArray([
                    'languageId' => 'LOWER(HEX(property_group_option_translation.language_id))',
                    'name' => 'property_group_option_translation.name',
                ], 'translations'),
                $sql
            ),
            [
                'ids' => Uuid::fromHexToBytesList($propertyIds),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        foreach ($options as $optionId => $option) {
            $translation = ElasticsearchIndexingUtils::parseJson($option, 'translations');

            $options[$optionId]['group'] = [
                'id' => $option['groupId'],
                '_count' => 1,
            ];
            $options[$optionId]['name'] = ElasticsearchFieldMapper::translated('name', $translation);
            unset($options[$optionId]['translations']);
        }

        return $options;
    }

    /**
     * @param array<string, array<string, array{gross: float, net: float, percentage: array{gross: float, net: float}}>> $cheapestPriceAccessor
     *
     * @return array<string, float>
     */
    private function mapCheapestPrice(array $cheapestPriceAccessor): array
    {
        $mapped = [];

        foreach ($cheapestPriceAccessor as $rule => $cheapestPriceCurrencies) {
            foreach ($cheapestPriceCurrencies as $currency => $taxes) {
                $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross';
                $mapped[$key] = $taxes['gross'];

                $key = 'cheapest_price_' . $rule . '_' . $currency . '_net';
                $mapped[$key] = $taxes['net'];

                if (empty($taxes['percentage'])) {
                    continue;
                }

                $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross_percentage';
                $mapped[$key] = $taxes['percentage']['gross'];

                $key = 'cheapest_price_' . $rule . '_' . $currency . '_net_percentage';
                $mapped[$key] = $taxes['percentage']['net'];
            }
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $variantCustomFields
     * @param array<string, mixed> $parentCustomFields
     *
     * @throws \JsonException
     *
     * @return array<string, array<string, mixed>>
     */
    private function mapCustomFields(array $variantCustomFields, array $parentCustomFields, Context $context): array
    {
        $customFields = [];

        $customFieldsLanguageIds = array_unique(array_merge(array_keys($parentCustomFields), array_keys($variantCustomFields)));

        foreach ($customFieldsLanguageIds as $languageId) {
            $merged = [];

            $chains = [
                $parentCustomFields[$languageId] ?? [],
                $variantCustomFields[$languageId] ?? [],
            ];

            /** @var array<mixed>|string $chain */
            foreach ($chains as $chain) {
                // chain is empty string, when no custom fields are set
                if ($chain === '') {
                    $chain = [];
                }

                if (\is_string($chain)) {
                    $chain = json_decode($chain, true, 512, \JSON_THROW_ON_ERROR);
                }

                foreach ($chain as $k => $v) {
                    if ($v === null) {
                        continue;
                    }

                    $merged[$k] = $v;
                }
            }

            $customFields[$languageId] = $merged;
        }

        return $this->fieldMapper->customFields(ProductDefinition::ENTITY_NAME, $customFields, $context);
    }
}
