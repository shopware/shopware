<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('merchant-services')]
enum ConsentState: string
{
    case REQUESTED = 'requested';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
}
