<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: float}
 */
#[Package('core')]
class SearchConfigLoader
{
    private const NOT_SUPPORTED_FIELDS = [
        'manufacturer.customFields',
        'categories.customFields',
    ];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        foreach ($context->getLanguageIdChain() as $languageId) {
            /** @var array<SearchConfig> $config */
            $config = $this->connection->fetchAllAssociative(
                'SELECT
product_search_config.and_logic,
product_search_config_field.field,
product_search_config_field.tokenize,
product_search_config_field.ranking

FROM product_search_config
INNER JOIN product_search_config_field ON(product_search_config_field.product_search_config_id = product_search_config.id)
WHERE product_search_config.language_id = :languageId AND product_search_config_field.searchable = 1 AND product_search_config_field.field NOT IN(:excludedFields)',
                [
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'excludedFields' => self::NOT_SUPPORTED_FIELDS,
                ],
                [
                    'excludedFields' => ArrayParameterType::STRING,
                ]
            );

            if (!empty($config)) {
                return $config;
            }
        }

        throw ElasticsearchProductException::configNotFound();
    }
}
