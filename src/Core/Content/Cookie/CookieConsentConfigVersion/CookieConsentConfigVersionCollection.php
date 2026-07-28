<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\CookieConsentConfigVersion;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 *
 * @extends EntityCollection<CookieConsentConfigVersionEntity>
 */
#[Package('framework')]
class CookieConsentConfigVersionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CookieConsentConfigVersionEntity::class;
    }
}
