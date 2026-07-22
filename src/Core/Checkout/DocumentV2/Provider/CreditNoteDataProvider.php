<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\CreditNoteRenderData;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopware\Core\Checkout\DocumentV2\Service\InvoiceRenderDataFactory;
use Shopware\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class CreditNoteDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'credit_note';

    public function __construct(
        private InvoiceRenderDataFactory $invoiceRenderDataFactory,
        private ReferenceInvoiceLoader $referenceInvoiceLoader,
        private CreditItemResolver $creditItemResolver,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::CREDIT_NOTE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceRenderDataFactory->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): CreditNoteRenderData {
        $documentNumber = $generationRequest->documentNumber;

        if ($documentNumber === null) {
            throw DocumentV2Exception::missingDocumentNumber($generationRequest->documentType);
        }

        [
            'id' => $referencedInvoiceId,
            'documentNumber' => $referencedInvoiceNumber,
        ] = $this->referenceInvoiceLoader->resolveReferencedInvoice(
            $order->getId(),
            $generationRequest->referencedDocumentId,
        );

        $this->creditItemResolver->apply($order, $referencedInvoiceId);

        $invoice = $this->invoiceRenderDataFactory->build(
            $order,
            $generationRequest,
            $context,
        );

        return CreditNoteRenderData::fromInvoice(
            $invoice,
            $documentNumber,
            $referencedInvoiceId,
            $referencedInvoiceNumber,
        );
    }
}
