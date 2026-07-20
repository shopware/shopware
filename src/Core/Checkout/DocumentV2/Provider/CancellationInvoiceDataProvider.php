<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Template\Calculation\OrderInverter;
use Shopware\Core\Checkout\DocumentV2\Template\Enum\TypeCode;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class CancellationInvoiceDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'storno';

    public function __construct(
        private InvoiceDataProvider $invoiceDataProvider,
        private ReferenceInvoiceLoader $referenceInvoiceLoader,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::CANCELLATION_INVOICE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceDataProvider->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): InvoiceRenderData {
        $referencedInvoiceNumber = $this->resolveReferencedInvoiceNumber(
            $order->getId(),
            $generationRequest->referencedDocumentId,
        );

        OrderInverter::invert($order);

        $invoice = $this->invoiceDataProvider->provideRenderingData($order, $generationRequest, $context);

        return $invoice->with(
            typeCode: TypeCode::CANCELLATION_INVOICE,
            custom: [
                'stornoNumber' => $generationRequest->documentNumber,
                'invoiceNumber' => $referencedInvoiceNumber,
            ],
        );
    }

    private function resolveReferencedInvoiceNumber(string $orderId, ?string $referencedDocumentId): string
    {
        $invoice = $this->referenceInvoiceLoader->load($orderId, $referencedDocumentId);

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

        return $number;
    }
}
