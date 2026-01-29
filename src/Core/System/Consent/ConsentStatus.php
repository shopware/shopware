<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
enum ConsentStatus: string
{
    case UNSET = 'unset';
    case ACCEPTED = 'accepted';
    case REVOKED = 'revoked';
}
