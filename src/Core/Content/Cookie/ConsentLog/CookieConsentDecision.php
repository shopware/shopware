<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * The verdict for one cookie group, derived by the server from the cookies the visitor ticked.
 *
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
enum CookieConsentDecision: string
{
    /**
     * Every cookie the visitor could tick in the group was accepted
     */
    case ACCEPTED = 'accepted';

    /**
     * Some, but not all, cookies of the group were accepted
     */
    case PARTIAL = 'partial';

    /**
     * No cookie of the group was accepted
     */
    case REJECTED = 'rejected';
}
