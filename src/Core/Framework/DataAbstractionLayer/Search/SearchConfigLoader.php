<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type SearchConfig array{and_logic: string, excluded_terms: array<string>, min_search_length: int, field: string, tokenize: int, ranking: float}
 */
#[Package('framework')]
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
            $config = $this->connection->fetchAllAssociative(
                'SELECT
product_search_config.and_logic,
LOWER(product_search_config.excluded_terms) as `excluded_terms`,
product_search_config.`min_search_length`,
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
                return array_map(function (array $item): array {
                    return [
                        'and_logic' => $item['and_logic'],
                        'excluded_terms' => json_decode($item['excluded_terms'], true),
                        'min_search_length' => (int) $item['min_search_length'],
                        'field' => $item['field'],
                        'tokenize' => (int) $item['tokenize'],
                        'ranking' => $item['ranking'],
                    ];
                }, $config);
            }
        }

        throw DataAbstractionLayerException::configNotFound();
    }
}
