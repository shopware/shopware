<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 *
 * @extends EntityCollection<CookieConsentLogEntity>
 */
#[Package('framework')]
class CookieConsentLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CookieConsentLogEntity::class;
    }
}
