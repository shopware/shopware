<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * Registers the strictly-necessary "age-verified" cookie set by the age verification CMS element.
 * It is added as a hidden entry to the required group: classified for the cookie machinery, but not
 * rendered as a toggle, mirroring the cookie-preference entry in the CookieProvider.
 *
 * @internal
 */
#[Package('discovery')]
class AgeVerificationCookieCollectListener
{
    public const COOKIE_NAME = 'age-verified';

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $requiredGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        if (!$requiredGroup) {
            return;
        }

        $entries = $requiredGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $requiredGroup->setEntries($entries);
        }

        $entry = new CookieEntry(self::COOKIE_NAME);
        $entry->name = 'cookie.groupRequiredAgeVerification';
        $entry->value = '1';
        $entry->expiration = 30;
        $entry->hidden = true;

        $entries->add($entry);
    }
}
