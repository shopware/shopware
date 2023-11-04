<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal - Fetch the $referenceDocumentId if set, otherwise fetch the latest document
 */
#[Package('customer-order')]
final class ReferenceInvoiceLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string>
     */
    public function load(string $orderId, ?string $referenceDocumentId = null, ?string $deepLinkCodeRendererConfig = null): array
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->select([
            'LOWER(HEX(`document`.`id`)) as id',
            'LOWER(HEX(`document`.`order_id`)) as orderId',
            'LOWER(HEX(`document`.`order_version_id`)) as orderVersionId',
            'LOWER(HEX(`order`.`version_id`)) as versionId',
            '`order`.`deep_link_code` as deepLinkCode',
            '`document`.`config` as config',
        ])->from('`document`', '`document`')
            ->innerJoin('`document`', '`document_type`', '`document_type`', '`document`.`document_type_id` = `document_type`.`id`')
            ->innerJoin('`document`', '`order`', '`order`', '`document`.`order_id` = `order`.`id`');

        $builder->where('`document_type`.`technical_name` = :techName')
            ->andWhere('`document`.`order_id` = :orderId');

        $builder->setParameters([
            'techName' => InvoiceRenderer::TYPE,
            'orderId' => Uuid::fromHexToBytes($orderId),
        ]);

        $builder->orderBy('`document`.`updated_at`', 'DESC');

        if (!empty($referenceDocumentId)) {
            $builder->andWhere('`document`.`id` = :documentId');
            $builder->setParameter('documentId', Uuid::fromHexToBytes($referenceDocumentId));
        }

        $documents = $builder->executeQuery()->fetchAllAssociative();

        if (empty($documents)) {
            return [];
        }

        $results = array_filter($documents, function (array $document) use ($deepLinkCodeRendererConfig) {
            if (!empty($deepLinkCodeRendererConfig)) {
                return $document['orderVersionId'] === $document['versionId']
                    && $deepLinkCodeRendererConfig === $document['deepLinkCode'];
            }

            return $document['orderVersionId'] === $document['versionId'];
        });

        // Set the order version ID to LIVE_VERSION if no matching documents were found
        $documents[0]['orderVersionId'] = Defaults::LIVE_VERSION;

        // Return the first document from the filtered results, or the first document if no filter was applied
        return empty($results) ? $documents[0] : reset($results);
    }
}
