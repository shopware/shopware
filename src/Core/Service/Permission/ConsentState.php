<?php declare(strict_types=1);

namespace Shopware\Core\Service\Permission;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum ConsentState: string
{
    case GRANTED = 'granted';
    case REVOKED = 'revoked';
}
