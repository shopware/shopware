<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Provider\AbstractDocumentDataProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.9.0 reason:experimental-replacement - Will be removed. Instead create own provider extending {@link AbstractDocumentDataProvider} with key {@link DocumentType::CANCELLATION_INVOICE} and extend order criteria via `enrichOrderCriteria()` or extend render data via `provideRenderingData()`.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class ZugferdCancellationInvoiceOrdersEvent extends DocumentOrderEvent
{
}
