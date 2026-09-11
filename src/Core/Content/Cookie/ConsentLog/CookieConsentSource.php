<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\ConsentLog;

use Shopware\Core\Framework\Log\Package;

/**
 * How a consent decision was collected. Follows the collection method of
 * ISO/IEC TS 27560. Only banner decisions are recorded today, the other
 * values exist so records from browser signals, integrations or imports
 * can share the schema without a migration.
 *
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
enum CookieConsentSource: string
{
    case BANNER = 'banner';
    case BROWSER_SIGNAL = 'browser_signal';
    case API = 'api';
    case IMPORTED = 'imported';
}
