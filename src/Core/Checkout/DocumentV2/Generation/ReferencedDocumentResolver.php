<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Generation;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Struct\ReferencedDocument;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Resolves the document a new document refers to, e.g. the invoice a cancellation invoice cancels.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class ReferencedDocumentResolver
{
    public function __construct(
        private ReferenceInvoiceLoader $referenceInvoiceLoader,
        private Connection $connection,
    ) {
    }

    /**
     * @throws DocumentV2Exception
     */
    public function resolve(string $orderId, ?string $referencedDocumentId): ReferencedDocument
    {
        if ($referencedDocumentId !== null && !Uuid::isValid($referencedDocumentId)) {
            throw DocumentV2Exception::referencedInvoiceNotFound($orderId);
        }

        $invoice = $this->referenceInvoiceLoader->load($orderId, $referencedDocumentId);

        if ($invoice === []) {
            throw DocumentV2Exception::referencedInvoiceNotFound($orderId);
        }

        $orderVersionId = $invoice['orderVersionId'] ?? '';

        if ($orderVersionId === '') {
            throw DocumentV2Exception::referencedOrderVersionNotFound($orderId);
        }

        if ($this->snapshotMissing($orderVersionId, $invoice['id'])) {
            throw DocumentV2Exception::referencedOrderVersionNotFound($orderId);
        }

        return new ReferencedDocument(
            id: $invoice['id'],
            documentNumber: $this->extractDocumentNumber($invoice, $orderId),
            orderVersionId: $orderVersionId,
        );
    }

    private function snapshotMissing(string $orderVersionId, string $documentId): bool
    {
        if ($orderVersionId !== Defaults::LIVE_VERSION) {
            return false;
        }

        $storedOrderVersionId = $this->connection->fetchOne(
            'SELECT `order_version_id` FROM `document` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($documentId)],
        );

        if (!\is_string($storedOrderVersionId)) {
            return true;
        }

        return Uuid::fromBytesToHex($storedOrderVersionId) !== Defaults::LIVE_VERSION;
    }

    /**
     * @param array<string, string> $invoice
     *
     * @throws DocumentV2Exception
     */
    private function extractDocumentNumber(array $invoice, string $orderId): string
    {
        $number = $invoice['documentNumber'] ?? null;

        if ($number === null || $number === '') {
            $config = json_decode($invoice['config'] ?? '[]', true, 512, \JSON_THROW_ON_ERROR);
            $number = $config['documentNumber'] ?? null;
        }

        if (!\is_string($number) || $number === '') {
            throw DocumentV2Exception::referencedInvoiceNumberMissing($orderId);
        }

        return $number;
    }
}
