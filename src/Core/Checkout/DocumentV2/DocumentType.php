<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * Document types implemented by shopware
 */
#[Package('after-sales')]
enum DocumentType: string
{
    case Invoice = 'invoice';
    case CancellationInvoice = 'cancellation_invoice'; // called 'storno' in the past
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
}
