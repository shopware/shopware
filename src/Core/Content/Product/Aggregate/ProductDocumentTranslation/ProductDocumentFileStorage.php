<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads the files of the language versions of product documents, and moves them to another file.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentFileStorageTest
 */
#[Package('inventory')]
class ProductDocumentFileStorage
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string> $documentIds
     * @param list<string> $versionIds
     *
     * @return array<string, array<string, array<string, string|null>>> file id per language id, per version id, per document id
     */
    public function getFiles(array $documentIds, array $versionIds): array
    {
        /** @var list<array{document_id: string, version_id: string, language_id: string, media_id: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`product_document_id`)) AS `document_id`, LOWER(HEX(`product_document_version_id`)) AS `version_id`,
                    LOWER(HEX(`language_id`)) AS `language_id`, LOWER(HEX(`media_id`)) AS `media_id`
             FROM `product_document_translation`
             WHERE `product_document_id` IN (:documentIds) AND `product_document_version_id` IN (:versionIds)',
            ['documentIds' => Uuid::fromHexToBytesList($documentIds), 'versionIds' => Uuid::fromHexToBytesList($versionIds)],
            ['documentIds' => ArrayParameterType::BINARY, 'versionIds' => ArrayParameterType::BINARY],
        );

        $files = [];
        foreach ($rows as $row) {
            $files[$row['document_id']][$row['version_id']][$row['language_id']] = $row['media_id'];
        }

        return $files;
    }

    /**
     * Gives each language version its new file, unless its file changed in the meantime.
     *
     * @param list<array{documentId: string, versionId: string, languageId: string, oldFileId: string, newFileId: string}> $moves
     */
    public function moveFiles(array $moves): void
    {
        $updatedAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($moves as $move) {
            $this->connection->executeStatement(
                'UPDATE `product_document_translation` SET `media_id` = :newFileId, `updated_at` = :updatedAt
                 WHERE `product_document_id` = :documentId AND `product_document_version_id` = :versionId
                   AND `language_id` = :languageId AND `media_id` = :oldFileId',
                [
                    'newFileId' => Uuid::fromHexToBytes($move['newFileId']),
                    'updatedAt' => $updatedAt,
                    'documentId' => Uuid::fromHexToBytes($move['documentId']),
                    'versionId' => Uuid::fromHexToBytes($move['versionId']),
                    'languageId' => Uuid::fromHexToBytes($move['languageId']),
                    'oldFileId' => Uuid::fromHexToBytes($move['oldFileId']),
                ],
            );
        }
    }
}
