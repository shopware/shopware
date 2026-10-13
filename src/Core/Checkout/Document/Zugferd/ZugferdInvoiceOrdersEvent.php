<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use Shopware\Core\Checkout\Document\Event\DocumentOrderEvent;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Framework\Deprecation\BCChange\ExperimentalReplacement;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[ExperimentalReplacement(
    version: 'v6.9.0',
    feature: 'DOCUMENT_GENERATION_REWORK',
    replacement: AbstractDocumentDataProvider::class,
    description: 'Register a data provider for DocumentType::INVOICE. Enrich the order criteria via enrichOrderCriteria() and the render data via provideRenderingData().',
)]
final class ZugferdInvoiceOrdersEvent extends DocumentOrderEvent
{
}
