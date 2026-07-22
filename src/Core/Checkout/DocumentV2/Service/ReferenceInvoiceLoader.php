<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class ReferenceInvoiceLoader
{
    /**
     * Document type technical names that count as a referenceable invoice: the v2 invoice type
     * (which also matches v1 plain invoices) plus the two v1 ZUGFeRD invoice renderers.
     */
    private const INVOICE_TECHNICAL_NAMES = [
        DocumentType::INVOICE->value,
        ZugferdRenderer::TYPE,
        ZugferdEmbeddedRenderer::TYPE,
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array<string, string>
     */
    public function load(string $orderId, ?string $referenceDocumentId = null, ?string $deepLinkCodeRendererConfig = null): array
    {
        $builder = $this->connection->createQueryBuilder();

        $builder->select(
            'LOWER(HEX(`document`.`id`)) as id',
            'LOWER(HEX(`document`.`order_id`)) as orderId',
            'LOWER(HEX(`document`.`order_version_id`)) as orderVersionId',
            'LOWER(HEX(`order`.`version_id`)) as versionId',
            '`order`.`deep_link_code` as deepLinkCode',
            '`document`.`config` as config',
            '`document`.`document_number` as documentNumber',
        )->from('`document`', '`document`')
            ->innerJoin('`document`', '`document_type`', '`document_type`', '`document`.`document_type_id` = `document_type`.`id`')
            ->innerJoin('`document`', '`order`', '`order`', '`document`.`order_id` = `order`.`id`');

        $builder->where('`document_type`.`technical_name` IN (:technicalNames)')
            ->andWhere('`document`.`order_id` = :orderId');

        $builder->setParameter('technicalNames', self::INVOICE_TECHNICAL_NAMES, ArrayParameterType::STRING);
        $builder->setParameter('orderId', Uuid::fromHexToBytes($orderId));

        $builder->orderBy('`document`.`sent`', 'DESC');
        $builder->addOrderBy('`document`.`created_at`', 'DESC');

        if ($referenceDocumentId && Uuid::isValid($referenceDocumentId)) {
            $builder->andWhere('`document`.`id` = :documentId');
            $builder->setParameter('documentId', Uuid::fromHexToBytes($referenceDocumentId));
        }

        $documents = $builder->executeQuery()->fetchAllAssociative();

        if ($documents === []) {
            return [];
        }

        $results = array_filter($documents, static function (array $document) use ($deepLinkCodeRendererConfig) {
            if ($deepLinkCodeRendererConfig !== null && $deepLinkCodeRendererConfig !== '') {
                return $document['orderVersionId'] === $document['versionId']
                    && $deepLinkCodeRendererConfig === $document['deepLinkCode'];
            }

            return $document['orderVersionId'] === $document['versionId'];
        });

        // Set the order version ID to LIVE_VERSION if no matching documents were found
        $documents[0]['orderVersionId'] = Defaults::LIVE_VERSION;

        // Return the first document from the filtered results, or the first document if no filter was applied
        return $results === [] ? $documents[0] : reset($results);
    }

    /**
     * Resolves the invoice a document references, throwing when it is missing or has no number.
     *
     * @throws DocumentV2Exception
     *
     * @return array{id: string, documentNumber: string}
     */
    public function resolveReferencedInvoice(string $orderId, ?string $referencedDocumentId): array
    {
        $invoice = $this->load($orderId, $referencedDocumentId);

        if ($invoice === []) {
            throw DocumentV2Exception::referencedInvoiceNotFound($orderId);
        }

        $number = $invoice['documentNumber'] ?? null;

        if ($number === null || $number === '') {
            $config = json_decode($invoice['config'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $number = $config['documentNumber'] ?? null;
        }

        if (!\is_string($number) || $number === '') {
            throw DocumentV2Exception::referencedInvoiceNumberMissing($orderId);
        }

        return [
            'id' => $invoice['id'],
            'documentNumber' => $number,
        ];
    }
}
