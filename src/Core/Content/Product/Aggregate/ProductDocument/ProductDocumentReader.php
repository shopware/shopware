<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Database reads for ProductDocumentValidator.
 * A product's key is its id and version id in hex, one after the other.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentReaderTest
 */
#[Package('inventory')]
class ProductDocumentReader
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $products
     *
     * @return array<string, int> number of documents per product; products without any are left out
     */
    public function countDocuments(array $products): array
    {
        if ($products === []) {
            return [];
        }

        [$productIds, $versionIds] = $this->splitKeys($products);

        /** @var array<string, string> $counts */
        $counts = $this->connection->fetchAllKeyValue(
            'SELECT CONCAT(LOWER(HEX(`product_id`)), LOWER(HEX(`product_version_id`))), COUNT(*)
            FROM `product_document`
            WHERE `product_id` IN (:productIds) AND `product_version_id` IN (:versionIds)
            GROUP BY `product_id`, `product_version_id`',
            ['productIds' => $productIds, 'versionIds' => $versionIds],
            ['productIds' => ArrayParameterType::BINARY, 'versionIds' => ArrayParameterType::BINARY],
        );

        return array_map(intval(...), array_intersect_key($counts, array_flip($products)));
    }

    /**
     * @param list<string> $keys
     *
     * @return array{array<string>, array<string>} the ids and the version ids, binary
     */
    private function splitKeys(array $keys): array
    {
        return [
            Uuid::fromHexToBytesList(array_values(array_unique(array_map(static fn (string $key): string => substr($key, 0, 32), $keys)))),
            Uuid::fromHexToBytesList(array_values(array_unique(array_map(static fn (string $key): string => substr($key, 32), $keys)))),
        ];
    }
}
