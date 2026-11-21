<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Hook\CookieGroupCollectHook;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CookieProvider
{
    final public const SNIPPET_NAME_COOKIE_GROUP_REQUIRED = 'cookie.groupRequired';
    final public const SNIPPET_NAME_COOKIE_GROUP_STATISTICAL = 'cookie.groupStatistical';
    final public const SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES = 'cookie.groupComfortFeatures';
    final public const SNIPPET_NAME_COOKIE_GROUP_MARKETING = 'cookie.groupMarketing';
    final public const COOKIE_ENTRY_CONFIG_HASH_COOKIE = 'cookie-config-hash';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly ScriptExecutor $scriptExecutor,
        array $sessionOptions = [],
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieGroupCollection
    {
        $cookieGroups = new CookieGroupCollection();

        $cookieGroups->add($this->getCookieGroupRequiredEntries());
        $cookieGroups->add($this->getCookieGroupStatistical());
        $cookieGroups->add($this->getCookieGroupComfortFeatures());
        $cookieGroups->add($this->getCookieGroupMarketing());

        $this->eventDispatcher->dispatch(new CookieGroupCollectEvent($cookieGroups, $request, $salesChannelContext));

        $this->scriptExecutor->execute(new CookieGroupCollectHook($cookieGroups, $salesChannelContext));

        foreach ($cookieGroups as $cookieGroup) {
            $this->removeCookieGroupsWithoutCookies($cookieGroups, $cookieGroup);
            $this->translateCookieGroupsAndTheirEntries($cookieGroup);
        }

        return $cookieGroups;
    }

    private function getCookieGroupRequiredEntries(): CookieGroup
    {
        $cookieGroupRequired = new CookieGroup(self::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $cookieGroupRequired->description = 'cookie.groupRequiredDescription';
        $cookieGroupRequired->setEntries(new CookieEntryCollection([
            $this->getRequiredSessionEntry(),
            $this->getRequiredTimezoneEntry(),
            $this->getRequiredAcceptedEntry(),
            $this->getRequiredCookieConfigHashEntry(),
        ]));
        $cookieGroupRequired->isRequired = true;

        return $cookieGroupRequired;
    }

    private function getRequiredSessionEntry(): CookieEntry
    {
        $entryRequiredSession = new CookieEntry($this->sessionName);
        $entryRequiredSession->name = 'cookie.groupRequiredSession';

        return $entryRequiredSession;
    }

    private function getRequiredTimezoneEntry(): CookieEntry
    {
        $entryRequiredTimezone = new CookieEntry('timezone');
        $entryRequiredTimezone->name = 'cookie.groupRequiredTimezone';

        return $entryRequiredTimezone;
    }

    private function getRequiredAcceptedEntry(): CookieEntry
    {
        $entryRequiredAccepted = new CookieEntry('cookie-preference');
        $entryRequiredAccepted->name = 'cookie.groupRequiredAccepted';
        $entryRequiredAccepted->value = '1';
        $entryRequiredAccepted->expiration = 30;
        $entryRequiredAccepted->hidden = true;

        return $entryRequiredAccepted;
    }

    private function getRequiredCookieConfigHashEntry(): CookieEntry
    {
        $entryRequiredCookieHash = new CookieEntry(self::COOKIE_ENTRY_CONFIG_HASH_COOKIE);
        $entryRequiredCookieHash->name = 'cookie.groupRequiredCookieHash';
        $entryRequiredCookieHash->hidden = true;

        return $entryRequiredCookieHash;
    }

    private function getCookieGroupStatistical(): CookieGroup
    {
        $cookieGroupStatistical = new CookieGroup(self::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $cookieGroupStatistical->setEntries(new CookieEntryCollection([]));
        $cookieGroupStatistical->description = 'cookie.groupStatisticalDescription';

        return $cookieGroupStatistical;
    }

    private function getCookieGroupComfortFeatures(): CookieGroup
    {
        $cookieGroupComfortFeatures = new CookieGroup(self::SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES);
        $cookieGroupComfortFeatures->setEntries(new CookieEntryCollection([
            $this->getYoutubeVideoEntry(),
            $this->getVimeoVideoEntry(),
        ]));

        return $cookieGroupComfortFeatures;
    }

    private function getYoutubeVideoEntry(): CookieEntry
    {
        $entryYoutubeVideo = new CookieEntry('youtube-video');
        $entryYoutubeVideo->name = 'cookie.groupComfortFeaturesYoutubeVideo';
        $entryYoutubeVideo->value = '1';
        $entryYoutubeVideo->expiration = 30;

        return $entryYoutubeVideo;
    }

    private function getVimeoVideoEntry(): CookieEntry
    {
        $entryVimeoVideo = new CookieEntry('vimeo-video');
        $entryVimeoVideo->name = 'cookie.groupComfortFeaturesVimeoVideo';
        $entryVimeoVideo->value = '1';
        $entryVimeoVideo->expiration = 30;

        return $entryVimeoVideo;
    }

    private function getCookieGroupMarketing(): CookieGroup
    {
        $cookieGroupMarketing = new CookieGroup(self::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $cookieGroupMarketing->description = 'cookie.groupMarketingDescription';
        $cookieGroupMarketing->setEntries(new CookieEntryCollection([]));

        return $cookieGroupMarketing;
    }

    private function removeCookieGroupsWithoutCookies(CookieGroupCollection $cookieGroups, CookieGroup $cookieGroup): void
    {
        // If the group is a cookie itself, it cannot have cookie entries but needs to be kept
        if ($cookieGroup->getCookie() !== null) {
            return;
        }

        $entries = $cookieGroup->getEntries();
        if ($entries === null || $entries->count() === 0) {
            // Cookie groups without cookie entries should not be shown to the user
            $cookieGroups->remove($cookieGroup->getTechnicalName());
        }
    }

    private function translateCookieGroupsAndTheirEntries(CookieGroup $cookieGroup): void
    {
        $cookieGroup->name = $this->translator->trans($cookieGroup->name);

        if (isset($cookieGroup->description)) {
            $cookieGroup->description = $this->translator->trans($cookieGroup->description);
        }

        $entries = $cookieGroup->getEntries();
        if ($entries !== null) {
            foreach ($entries as $entry) {
                if (isset($entry->name)) {
                    $entry->name = $this->translator->trans($entry->name);
                }

                if (isset($entry->description)) {
                    $entry->description = $this->translator->trans($entry->description);
                }
            }
        }
    }
}
