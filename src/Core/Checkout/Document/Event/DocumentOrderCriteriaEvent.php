<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
final class DocumentOrderCriteriaEvent extends Event implements GenericEvent
{
    public const CREDIT_NOTE_ORDER_CRITERIA_EVENT = CreditNoteRenderer::TYPE . '.document.criteria';
    public const DELIVERY_ORDER_CRITERIA_EVENT = DeliveryNoteRenderer::TYPE . '.document.criteria';
    public const INVOICE_ORDER_CRITERIA_EVENT = InvoiceRenderer::TYPE . '.document.criteria';
    public const STORNO_ORDER_CRITERIA_EVENT = StornoRenderer::TYPE . '.document.criteria';

    private readonly string $name;

    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly Criteria $criteria,
        private readonly Context $context,
        private readonly array $operations,
        private readonly DocumentRendererConfig $documentRendererConfig,
        string $documentType,
    ) {
        $this->name = $documentType . '.document.criteria';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getDocumentRendererConfig(): DocumentRendererConfig
    {
        return $this->documentRendererConfig;
    }
}
