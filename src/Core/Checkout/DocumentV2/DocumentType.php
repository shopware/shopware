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

    public function templatePath(): string
    {
        return match ($this) {
            self::INVOICE => '@Framework/documents/invoice.html.twig',
            self::DELIVERY_NOTE => '@Framework/documents/delivery_note.html.twig',
            self::CREDIT_NOTE => '@Framework/documents/credit_note.html.twig',
            self::CANCELLATION_INVOICE => '@Framework/documents/storno.html.twig',
        };
    }
}
