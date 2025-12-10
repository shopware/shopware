<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
enum ConsentScope: string
{
    case GLOBAL = 'global';
    case ADMIN_USER = 'admin_user';
}
