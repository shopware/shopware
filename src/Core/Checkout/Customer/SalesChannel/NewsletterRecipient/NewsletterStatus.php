<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-08-10 15:01:45
 */

namespace Shopware\Core\Checkout\Customer\SalesChannel\NewsletterRecipient;

use Shopware\Core\Framework\Log\Package;

/**
 * The subscription status. Possible values are: notSet, optIn, optOut, direct, undefined.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
enum NewsletterStatus: string
{
    case NOT_SET = 'notSet';
    case OPT_IN = 'optIn';
    case OPT_OUT = 'optOut';
    case DIRECT = 'direct';
    case UNDEFINED = 'undefined';
}
