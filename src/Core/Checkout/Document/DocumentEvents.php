<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class DocumentEvents
{
    public const CREDIT_NOTE_ORDER_CRITERIA_EVENT = CreditNoteRenderer::TYPE . '.document.criteria';
    public const DELIVERY_ORDER_CRITERIA_EVENT = DeliveryNoteRenderer::TYPE . '.document.criteria';
    public const INVOICE_ORDER_CRITERIA_EVENT = InvoiceRenderer::TYPE . '.document.criteria';
    public const STORNO_ORDER_CRITERIA_EVENT = StornoRenderer::TYPE . '.document.criteria';
}
