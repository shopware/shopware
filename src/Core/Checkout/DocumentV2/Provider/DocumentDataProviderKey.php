<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum DocumentDataProviderKey: string
{
    case META = 'meta';
    case INVOICE = 'invoice';
    case DELIVERY_NOTE = 'delivery_note';
    case CREDIT_NOTE = 'credit_note';
    case CANCELLATION_INVOICE = 'storno';
}
