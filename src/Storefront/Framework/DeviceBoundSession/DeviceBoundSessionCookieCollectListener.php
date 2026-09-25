<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * Lists the device bound session cookie as technically required, as it secures the session cookie.
 *
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionCookieCollectListener
{
    /**
     * The cookie name carries the session identifier, so the consent manager lists its prefix.
     * Without a value, the consent manager never sets or removes the cookie itself.
     */
    public const COOKIE_ENTRY = DeviceBoundSessionService::COOKIE_PREFIX . '*';

    public function __construct(private readonly bool $enabled)
    {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $requiredCookieGroup = $event->cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        if (!$requiredCookieGroup || !$requiredCookieGroup->isRequired) {
            return;
        }

        $entries = $requiredCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $requiredCookieGroup->setEntries($entries);
        }

        $entry = new CookieEntry(self::COOKIE_ENTRY);
        $entry->name = 'cookie.groupRequiredDeviceBoundSession';

        $entries->add($entry);
    }
}
