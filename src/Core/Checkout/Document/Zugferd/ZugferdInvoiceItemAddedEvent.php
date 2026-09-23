<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    description: 'ZUGFeRD XML is produced by the DocumentV2 XML renderer without a public builder API.',
)]
class ZugferdInvoiceItemAddedEvent extends Event
{
    public function __construct(
        public readonly ZugferdDocument $document,
        public readonly OrderLineItemEntity $lineItem,
        public readonly string $parentPosition
    ) {
    }
}
