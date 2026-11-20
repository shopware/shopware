<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\SearchKeyword\SearchConfigRankingCheckerTest
 */
#[Package('inventory')]
class SearchConfigRankingChecker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function hasNegativeRanking(string $languageId): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT 1
            FROM product_search_config_field configField
            INNER JOIN product_search_config config ON config.id = configField.product_search_config_id
            WHERE config.language_id IN (:languageIds)
            AND configField.searchable = 1
            AND configField.ranking < 0
            LIMIT 1',
            ['languageIds' => Uuid::fromHexToBytesList([$languageId, Defaults::LANGUAGE_SYSTEM])],
            ['languageIds' => ArrayParameterType::BINARY]
        );

        return $result !== false;
    }
}
