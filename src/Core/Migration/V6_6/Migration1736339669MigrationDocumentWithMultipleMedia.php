<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1736339669MigrationDocumentWithMultipleMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736339669;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $documents = $connection->fetchAllAssociative(
                <<<'SQL'
                    SELECT id, document_media_file_id
                    FROM `document`
                    WHERE document_media_file_id IS NOT NULL;
                SQL
            );

            $insertQuery = <<<'SQL'
                INSERT INTO document_media (id, media_id, document_id, file_extension, created_at)
                VALUES (:id, :media_id, :document_id, :file_extension, NOW());
            SQL;

            /** @var array{document_media_file_id: string, id: string} $document */
            foreach ($documents as $document) {
                $connection->executeStatement(
                    $insertQuery,
                    [
                        'id' => Uuid::randomBytes(),
                        'media_id' => $document['document_media_file_id'],
                        'document_id' => $document['id'],
                        'file_extension' => PdfRenderer::FILE_EXTENSION,
                    ]
                );
            }

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }
}
