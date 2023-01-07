<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package customer-order
 *
 * @internal - Fetch the $referenceDocumentId if set, otherwise fetch the latest document
 */
final class ReferenceInvoiceLoader
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(string $orderId, ?string $referenceDocumentId = null): array
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->select([
            'LOWER(HEX(`document`.`id`)) as `id`',
            'LOWER(HEX(`document`.`order_id`)) as `orderId`',
            'LOWER(HEX(`document`.`order_version_id`)) as `orderVersionId`',
            '`document`.`config` as `config`',
        ]);

        $builder
            ->from(DocumentDefinition::ENTITY_NAME)
            ->innerJoin(
                DocumentDefinition::ENTITY_NAME,
                DocumentTypeDefinition::ENTITY_NAME,
                DocumentTypeDefinition::ENTITY_NAME,
                '`document`.`document_type_id` = `document_type`.`id`'
            );

        $builder->where('`document_type`.`technical_name` = :techName')->andWhere('`document`.`order_id` = :orderId');
        $builder->setParameters([
            'techName' => InvoiceRenderer::TYPE,
            'orderId' => Uuid::fromHexToBytes($orderId),
        ]);

        $builder->orderBy('`document`.`created_at`', 'DESC');
        $builder->setMaxResults(1);

        if (!empty($referenceDocumentId)) {
            $builder->andWhere('`document`.`id` = :documentId');
            $builder->setParameter('documentId', Uuid::fromHexToBytes($referenceDocumentId));
        }

        $result = $builder->executeQuery()->fetchAssociative();

        return $result !== false ? $result : [];
    }
}
