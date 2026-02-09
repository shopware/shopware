<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * Document types implemented by shopware
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case CancellationInvoice = 'cancellation_invoice'; // called 'storno' in the past
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
}
