<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
enum ConsentState: string
{
    case REQUESTED = 'requested';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
}
