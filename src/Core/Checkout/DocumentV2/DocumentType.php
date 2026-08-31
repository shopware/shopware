<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum DocumentType: string
{
    case INVOICE = 'invoice';
    case DELIVERY_NOTE = 'delivery_note';
    case CREDIT_NOTE = 'credit_note';
    case CANCELLATION_INVOICE = 'storno';

    /**
     * Reserved technical name of the shared `document_type` row that every app-provided
     * document references. It is not a generatable type on its own: apps must never claim it as an identifier.
     *
     * @deprecated tag:v6.9.0 - reason:experimental-replacement - Remove together with the legacy `document_type` table
     */
    case APP_PROVIDED = 'app_provided';
}
