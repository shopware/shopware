<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * The interaction a visitor performed on the cookie banner.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum CookieConsentAction: string
{
    case ACCEPT_ALL = 'accept_all';
    case ACCEPT_REQUIRED = 'accept_required';
    case ACCEPT_SELECTED = 'accept_selected';
}
