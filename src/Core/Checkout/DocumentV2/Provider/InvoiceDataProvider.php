<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequest;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Service\InvoiceRenderDataFactory;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class InvoiceDataProvider extends AbstractDocumentDataProvider
{
    final public const KEY = 'invoice';

    public function __construct(
        private InvoiceRenderDataFactory $invoiceRenderDataFactory,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(string $documentType): bool
    {
        return $documentType === DocumentType::INVOICE->value;
    }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $this->invoiceRenderDataFactory->enrichOrderCriteria($criteria);
    }

    public function provideRenderingData(
        OrderEntity $order,
        DocumentGenerationRequest $generationRequest,
        Context $context,
    ): InvoiceRenderData {
        return $this->invoiceRenderDataFactory->build(
            $order,
            $generationRequest,
            $context
        );
    }
}
