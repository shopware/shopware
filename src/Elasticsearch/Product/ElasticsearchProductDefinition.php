<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;

class ElasticsearchProductDefinition extends AbstractElasticsearchDefinition
{
    private const PRODUCT_NAME_FIELDS = ['product_translation.translation.name', 'product_translation.translation.fallback_1.name', 'product_translation.translation.fallback_2.name'];
    private const PRODUCT_DESCRIPTION_FIELDS = ['product_translation.translation.description', 'product_translation.translation.fallback_1.description', 'product_translation.translation.fallback_2.description'];
    private const PRODUCT_CUSTOM_FIELDS = ['product_translation.translation.custom_fields', 'product_translation.translation.fallback_1.custom_fields', 'product_translation.translation.fallback_2.custom_fields'];

    protected ProductDefinition $definition;

    private Connection $connection;

    private CashRounding $rounding;

    private PriceFieldSerializer $priceFieldSerializer;

    private ?array $customFieldsTypes = null;

    public function __construct(
        ProductDefinition $definition,
        EntityMapper $mapper,
        Connection $connection,
        CashRounding $rounding,
        PriceFieldSerializer $priceFieldSerializer
    ) {
        parent::__construct($mapper);
        $this->definition = $definition;
        $this->connection = $connection;
        $this->rounding = $rounding;
        $this->priceFieldSerializer = $priceFieldSerializer;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getMapping(Context $context): array
    {
        return [
            '_source' => ['includes' => ['id']],
            'properties' => [
                'id' => EntityMapper::KEYWORD_FIELD,
                'parentId' => EntityMapper::KEYWORD_FIELD,
                'active' => EntityMapper::BOOLEAN_FIELD,
                'categoriesRo' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                    ],
                ],
                'childCount' => EntityMapper::INT_FIELD,
                'description' => EntityMapper::KEYWORD_FIELD,
                'displayGroup' => EntityMapper::KEYWORD_FIELD,
                'height' => EntityMapper::FLOAT_FIELD,
                'manufacturer' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                    ],
                ],
                'name' => EntityMapper::KEYWORD_FIELD,
                'options' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                    ],
                ],
                'productNumber' => EntityMapper::KEYWORD_FIELD,
                'properties' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                    ],
                ],
                'ratingAverage' => EntityMapper::FLOAT_FIELD,
                'releaseDate' => [
                    'type' => 'date',
                ],
                'sales' => EntityMapper::INT_FIELD,
                'stock' => EntityMapper::INT_FIELD,
                'shippingFree' => EntityMapper::BOOLEAN_FIELD,
                'taxId' => EntityMapper::KEYWORD_FIELD,
                'tags' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                    ],
                ],
                'visibilities' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => EntityMapper::KEYWORD_FIELD,
                        'visibility' => EntityMapper::INT_FIELD,
                    ],
                ],
                'weight' => EntityMapper::FLOAT_FIELD,
                'width' => EntityMapper::FLOAT_FIELD,
                'customFields' => $this->getCustomFieldsMapping(),
            ],
            'dynamic_templates' => [
                [
                    'cheapest_price' => [
                        'match' => 'cheapest_price_rule*',
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

    public function extendDocuments(array $documents, Context $context): array
    {
        return $documents;
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        $query = parent::buildTermQuery($context, $criteria);

        $query->add(
            new MatchQuery('description', (string) $criteria->getTerm()),
            BoolQuery::SHOULD
        );

        return $query;
    }

    public function fetch(array $ids, Context $context): array
    {
        $data = $this->fetchProducts($ids, $context);

        $currencies = $context->getExtension('currencies');

        if (!$currencies instanceof EntityCollection) {
            throw new \RuntimeException('Currencies are required for indexing process');
        }

        $tmpField = new PriceField('purchasePrices', 'purchasePrices');

        $documents = [];

        foreach ($data as $id => $item) {
            $visibilities = array_filter(explode('|', $item['visibilities'] ?? ''));

            $visibilities = array_map(function (string $text) {
                [$visibility, $salesChannelId] = explode(',', $text);

                return [
                    'visibility' => $visibility,
                    'salesChannelId' => $salesChannelId,
                ];
            }, $visibilities);

            $prices = [];
            $purchase = [];

            $purchasePrices = $this->priceFieldSerializer->decode($tmpField, $item['purchasePrices']);
            $price = $this->priceFieldSerializer->decode($tmpField, $item['price']);

            foreach ($currencies as $currency) {
                $key = 'c_' . $currency->getId();

                $prices[$key] = $this->getCurrencyPrice($id, $price, $currency);
                $purchase[$key] = $this->getCurrencyPurchasePrice($purchasePrices, $currency);
            }

            $document = [
                'id' => $id,
                'name' => $this->stripText($item['name'] ?? ''),
                'ratingAverage' => (float) $item['ratingAverage'],
                'active' => (bool) $item['active'],
                'shippingFree' => (bool) $item['shippingFree'],
                'customFields' => $this->formatCustomFields($item['customFields'] ? json_decode($item['customFields'], true) : []),
                'visibilities' => $visibilities,
                'availableStock' => (int) $item['availableStock'],
                'productNumber' => $item['productNumber'],
                'displayGroup' => $item['displayGroup'],
                'sales' => (int) $item['sales'],
                'stock' => (int) $item['stock'],
                'description' => $this->stripText((string) $item['description']),
                'weight' => (float) $item['weight'],
                'width' => (float) $item['width'],
                'length' => (float) $item['length'],
                'height' => (float) $item['height'],
                'price' => $prices,
                'purchasePrices' => $purchase,
                'manufacturerId' => $item['productManufacturerId'],
                'manufacturer' => [
                    'id' => $item['productManufacturerId'],
                ],
                'releaseDate' => isset($item['releaseDate']) ? (new \DateTime($item['releaseDate']))->format('c') : null,
                'optionIds' => json_decode($item['optionIds'] ?? '[]', true),
                'options' => array_map(fn (string $optionId) => ['id' => $optionId], json_decode($item['optionIds'] ?? '[]', true)),
                'categoriesRo' => array_map(fn (string $categoryId) => ['id' => $categoryId], json_decode($item['categoryIds'] ?? '[]', true)),
                'properties' => array_map(fn (string $propertyId) => ['id' => $propertyId], json_decode($item['propertyIds'] ?? '[]', true)),
                'propertyIds' => json_decode($item['propertyIds'] ?? '[]', true),
                'taxId' => $item['taxId'],
                'tags' => array_map(fn (string $tagId) => ['id' => $tagId], json_decode($item['tagIds'] ?? '[]', true)),
                'tagIds' => json_decode($item['tagIds'] ?? '[]', true),
                'parentId' => $item['parentId'],
                'childCount' => (int) $item['childCount'],
                'fullText' => $this->stripText(implode(' ', [$item['name'], $item['description'], $item['productNumber']])),
                'fullTextBoosted' => $this->stripText(implode(' ', [$item['name'], $item['description'], $item['productNumber']])),
            ];

            if ($item['cheapest_price_accessor']) {
                $cheapestPriceAccessor = json_decode($item['cheapest_price_accessor'], true);

                foreach ($cheapestPriceAccessor as $rule => $cheapestPriceCurrencies) {
                    foreach ($cheapestPriceCurrencies as $currency => $taxes) {
                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_gross';
                        $document[$key] = $taxes['gross'];

                        $key = 'cheapest_price_' . $rule . '_' . $currency . '_net';
                        $document[$key] = $taxes['net'];
                    }
                }
            }

            $documents[$id] = $document;
        }

        return $documents;
    }

    private function buildCoalesce(array $fields, Context $context): string
    {
        $fields = array_splice($fields, 0, \count($context->getLanguageIdChain()));

        $coalesce = 'COALESCE(';

        foreach (['product_translation_main', 'product_translation_parent'] as $join) {
            foreach ($fields as $field) {
                $coalesce .= sprintf('%s.`%s`', $join, $field) . ',';
            }
        }

        return substr($coalesce, 0, -1) . ')';
    }

    private function getTranslationQuery(Context $context): QueryBuilder
    {
        $table = $this->definition->getEntityName() . '_translation';

        $query = new QueryBuilder($this->connection);

        $select = '`#alias#`.name  as `#alias#.name`, `#alias#`.description  as `#alias#.description`, `#alias#`.custom_fields  as `#alias#.custom_fields`';

        // first language has to be the from part, in this case we have to use the system language to enforce we have a record
        $chain = $context->getLanguageIdChain();

        $first = array_shift($chain);
        $firstAlias = 'product_translation.translation';

        $foreignKey = EntityDefinitionQueryHelper::escape($firstAlias) . '.' . $this->definition->getEntityName() . '_id';

        // used as join condition
        $query->addSelect($foreignKey);

        // set first language as from part
        $query->addSelect(str_replace('#alias#', $firstAlias, $select));
        $query->from(EntityDefinitionQueryHelper::escape($table), EntityDefinitionQueryHelper::escape($firstAlias));
        $query->where(EntityDefinitionQueryHelper::escape($firstAlias) . '.language_id = :languageId');
        $query->andWhere(EntityDefinitionQueryHelper::escape($firstAlias) . '.product_id IN(:ids)');
        $query->andWhere(EntityDefinitionQueryHelper::escape($firstAlias) . '.product_version_id = :liveVersionId');
        $query->setParameter('languageId', Uuid::fromHexToBytes($first));

        foreach ($chain as $i => $language) {
            ++$i;

            $condition = '#firstAlias#.#column# = #alias#.#column# AND #alias#.language_id = :languageId' . $i;

            $alias = 'product_translation.translation.fallback_' . $i;

            $variables = [
                '#column#' => EntityDefinitionQueryHelper::escape($this->definition->getEntityName() . '_id'),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#firstAlias#' => EntityDefinitionQueryHelper::escape($firstAlias),
            ];

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($firstAlias),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                str_replace(array_keys($variables), array_values($variables), $condition)
            );

            $query->addSelect(str_replace('#alias#', $alias, $select));
            $query->setParameter('languageId' . $i, Uuid::fromHexToBytes($language));
        }

        return $query;
    }

    private function getCurrencyPrice(string $id, ?PriceCollection $prices, CurrencyEntity $currency): array
    {
        if ($prices === null) {
            return [];
        }

        $origin = $prices->getCurrencyPrice($currency->getId());

        if (!$origin) {
            throw new \RuntimeException(sprintf('Missing default price for product %s', $id));
        }

        return $this->getPrice($origin, $currency);
    }

    private function getCurrencyPurchasePrice(?PriceCollection $prices, CurrencyEntity $currency): array
    {
        if ($prices === null) {
            return [];
        }

        if ($prices->count() === 0) {
            return [];
        }

        $origin = $prices->getCurrencyPrice($currency->getId());

        if (!$origin) {
            return [];
        }

        return $this->getPrice(clone $origin, $currency);
    }

    private function getPrice(Price $origin, CurrencyEntity $currency): array
    {
        $price = clone $origin;

        // fallback price returned?
        if ($price->getCurrencyId() !== $currency->getId()) {
            $price->setGross($price->getGross() * $currency->getFactor());
            $price->setNet($price->getNet() * $currency->getFactor());
        }

        $config = $currency->getItemRounding();

        $price->setGross(
            $this->rounding->cashRound($price->getGross(), $config)
        );

        if ($config->roundForNet()) {
            $price->setNet(
                $this->rounding->cashRound($price->getNet(), $config)
            );
        }

        return json_decode(JsonFieldSerializer::encodeJson($price), true);
    }

    private function fetchProducts(array $ids, Context $context): array
    {
        $sql = <<<'SQL'
SELECT
    LOWER(HEX(p.id)) AS id,
    IFNULL(p.active, pp.active) AS active,
    :nameTranslated: AS name,
    :descriptionTranslated: AS description,
    :customFieldsTranslated: AS customFields,
    IFNULL(p.available_stock, pp.available_stock) AS availableStock,
    IFNULL(p.rating_average, pp.rating_average) AS ratingAverage,
    p.product_number as productNumber,
    p.sales,
    LOWER(HEX(IFNULL(p.product_manufacturer_id, pp.product_manufacturer_id))) AS productManufacturerId,
    IFNULL(p.shipping_free, pp.shipping_free) AS shippingFree,
    IFNULL(p.is_closeout, pp.is_closeout) AS isCloseout,
    IFNULL(p.weight, pp.weight) AS weight,
    IFNULL(p.length, pp.length) AS length,
    IFNULL(p.height, pp.height) AS height,
    IFNULL(p.width, pp.width) AS width,
    IFNULL(p.release_date, pp.release_date) AS releaseDate,
    IFNULL(p.category_tree, pp.category_tree) AS categoryIds,
    IFNULL(p.option_ids, pp.option_ids) AS optionIds,
    IFNULL(p.property_ids, pp.property_ids) AS propertyIds,
    IFNULL(p.tag_ids, pp.tag_ids) AS tagIds,
    LOWER(HEX(IFNULL(p.tax_id, pp.tax_id))) AS taxId,
    IFNULL(p.stock, pp.stock) AS stock,
    p.purchase_prices as purchasePrices,
    p.price as price,
    GROUP_CONCAT(CONCAT(product_visibility.visibility, ',', LOWER(HEX(product_visibility.sales_channel_id))) SEPARATOR '|') AS visibilities,
    p.display_group as displayGroup,
    IFNULL(p.cheapest_price_accessor, pp.cheapest_price_accessor) as cheapest_price_accessor,
    LOWER(HEX(p.parent_id)) as parentId,
    p.child_count as childCount

FROM product p
    LEFT JOIN product pp ON(p.parent_id = pp.id AND pp.version_id = :liveVersionId)
    LEFT JOIN product_visibility ON(product_visibility.product_id = p.visibilities AND product_visibility.product_version_id = :liveVersionId)

    LEFT JOIN (
        :productTranslationQuery:
    ) product_translation_main ON (product_translation_main.product_id = p.id)

    LEFT JOIN (
        :productTranslationQuery:
    ) product_translation_parent ON (product_translation_parent.product_id = p.parent_id)

WHERE p.id IN (:ids) AND p.version_id = :liveVersionId AND (p.child_count = 0 OR p.parent_id IS NOT NULL)

GROUP BY p.id
SQL;
        $translationQuery = $this->getTranslationQuery($context);

        $replacements = [
            ':productTranslationQuery:' => $translationQuery->getSQL(),
            ':nameTranslated:' => $this->buildCoalesce(self::PRODUCT_NAME_FIELDS, $context),
            ':descriptionTranslated:' => $this->buildCoalesce(self::PRODUCT_DESCRIPTION_FIELDS, $context),
            ':customFieldsTranslated:' => $this->buildCoalesce(self::PRODUCT_CUSTOM_FIELDS, $context),
        ];

        $data = $this->connection->fetchAll(
            str_replace(array_keys($replacements), array_values($replacements), $sql),
            array_merge([
                'ids' => $ids,
                'liveVersionId' => Uuid::fromHexToBytes($context->getVersionId()),
            ], $translationQuery->getParameters()),
            [
                'ids' => Connection::PARAM_STR_ARRAY,
            ]
        );

        return FetchModeHelper::groupUnique($data);
    }

    private function getCustomFieldsMapping(): array
    {
        $fieldMapping = $this->getCustomFieldTypes();
        $mapping = [
            'type' => 'object',
            'dynamic' => true,
            'properties' => [],
        ];

        foreach ($fieldMapping as $name => $type) {
            $esType = CustomFieldUpdater::getTypeFromCustomFieldType($type);

            if ($esType === null) {
                continue;
            }

            $mapping['properties'][$name] = $esType;
        }

        return $mapping;
    }

    private function formatCustomFields(array $customFields): array
    {
        $types = $this->getCustomFieldTypes();

        foreach ($customFields as $name => $customField) {
            $type = $types[$name] ?? null;
            if ($type === CustomFieldTypes::BOOL) {
                $customFields[$name] = (bool) $customField;
            } elseif (\is_int($customField)) {
                $customFields[$name] = (float) $customField;
            }
        }

        return $customFields;
    }

    private function getCustomFieldTypes(): array
    {
        if ($this->customFieldsTypes !== null) {
            return $this->customFieldsTypes;
        }

        return $this->customFieldsTypes = $this->connection->fetchAllKeyValue('
SELECT
    custom_field.`name`,
    custom_field.type
FROM custom_field_set_relation
    INNER JOIN custom_field ON(custom_field.set_id = custom_field_set_relation.set_id)
WHERE custom_field_set_relation.entity_name = "product"
');
    }
}
