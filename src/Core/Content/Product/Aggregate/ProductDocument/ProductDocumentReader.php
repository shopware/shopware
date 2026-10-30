<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocument;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads what ProductDocumentValidator needs from the database. Products and documents are keyed by their id and
 * version id, both hex, joined.
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
     * @param list<string> $mediaIds hex
     *
     * @return array<string, array{extension: string|null, size: int|null, private: bool}> keyed by media id
     */
    public function getFiles(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return [];
        }

        /** @var array<string, array{file_extension: string|null, file_size: string|null, private: string}> $rows */
        $rows = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`id`)), `file_extension`, `file_size`, `private` FROM `media` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($mediaIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return array_map(static fn (array $row): array => [
            'extension' => $row['file_extension'],
            'size' => $row['file_size'] === null ? null : (int) $row['file_size'],
            'private' => (bool) $row['private'],
        ], $rows);
    }

    /**
     * @param list<string> $products
     *
     * @return array<string, int> the number of stored documents of each product that has any
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
     * The translations of the documents whose file another document of the same product has, in any language.
     *
     * @param list<string> $documents
     *
     * @return list<array{document: string, languageId: string, mediaId: string}>
     */
    public function getSharedFiles(array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        [$ids, $versionIds] = $this->splitKeys($documents);

        /** @var list<array{document: string, languageId: string, mediaId: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT
                CONCAT(LOWER(HEX(`translation`.`product_document_id`)), LOWER(HEX(`translation`.`product_document_version_id`))) AS `document`,
                LOWER(HEX(`translation`.`language_id`)) AS `languageId`,
                LOWER(HEX(`translation`.`media_id`)) AS `mediaId`
            FROM `product_document_translation` `translation`
            INNER JOIN `product_document` `document`
                ON `document`.`id` = `translation`.`product_document_id`
                AND `document`.`version_id` = `translation`.`product_document_version_id`
            INNER JOIN `product_document` `other_document`
                ON `other_document`.`product_id` = `document`.`product_id`
                AND `other_document`.`product_version_id` = `document`.`product_version_id`
                AND `other_document`.`id` != `document`.`id`
            INNER JOIN `product_document_translation` `other_translation`
                ON `other_translation`.`product_document_id` = `other_document`.`id`
                AND `other_translation`.`product_document_version_id` = `other_document`.`version_id`
                AND `other_translation`.`media_id` = `translation`.`media_id`
            WHERE `translation`.`product_document_id` IN (:ids)
                AND `translation`.`product_document_version_id` IN (:versionIds)',
            ['ids' => $ids, 'versionIds' => $versionIds],
            ['ids' => ArrayParameterType::BINARY, 'versionIds' => ArrayParameterType::BINARY],
        );

        $documents = array_flip($documents);

        return array_values(array_filter($rows, static fn (array $row): bool => isset($documents[$row['document']])));
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
