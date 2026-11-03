<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Reads the files of the default-language versions of product documents.
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
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $documentIds
     *
     * @return array<string, string> file id per document id
     */
    public function getDefaultFiles(array $documentIds, string $versionId): array
    {
        /** @var array<string, string> */
        return $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`product_document_id`)), LOWER(HEX(`media_id`))
             FROM `product_document_translation`
             WHERE `product_document_id` IN (:documentIds) AND `product_document_version_id` = :versionId
               AND `language_id` = :languageId AND `media_id` IS NOT NULL',
            [
                'documentIds' => Uuid::fromHexToBytesList($documentIds),
                'versionId' => Uuid::fromHexToBytes($versionId),
                'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ],
            ['documentIds' => ArrayParameterType::BINARY],
        );
    }
}
