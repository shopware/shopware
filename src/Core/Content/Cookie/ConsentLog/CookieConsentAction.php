<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * The interaction a visitor performed on the cookie banner.
 *
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
enum CookieConsentAction: string
{
    case ACCEPT_ALL = 'accept_all';
    case ACCEPT_REQUIRED = 'accept_required';
    case ACCEPT_SELECTED = 'accept_selected';
}
