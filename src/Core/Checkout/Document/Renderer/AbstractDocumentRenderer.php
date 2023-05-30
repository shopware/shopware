<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('customer-order')]
abstract class AbstractDocumentRenderer
{
    abstract public function supports(): string;

    /**
     * @param DocumentGenerateOperation[] $operations
     */
    abstract public function render(array $operations, Context $context, DocumentRendererConfig $rendererConfig): RendererResult;

    abstract public function getDecorated(): AbstractDocumentRenderer;

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getOrdersLanguageId(array $ids, string $versionId, Connection $connection): array
    {
        return $connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(language_id)) as language_id, GROUP_CONCAT(DISTINCT LOWER(HEX(id))) as ids
            FROM `order`
            WHERE `id` IN (:ids)
            AND `version_id` = :versionId
            AND `language_id` IS NOT NULL
            GROUP BY `language_id`',
            ['ids' => Uuid::fromHexToBytesList($ids), 'versionId' => Uuid::fromHexToBytes($versionId)],
            ['ids' => ArrayParameterType::STRING]
        );
    }
}
