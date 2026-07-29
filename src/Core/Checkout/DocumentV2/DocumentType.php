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
    case CANCELLATION_INVOICE = 'cancellation_invoice';
}
