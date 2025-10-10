<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
 */
#[Package('framework')]
class CookieProvider
{
    final public const SNIPPET_NAME_COOKIE_GROUP_REQUIRED = 'cookie.groupRequired';
    final public const SNIPPET_NAME_COOKIE_GROUP_STATISTICAL = 'cookie.groupStatistical';
    final public const SNIPPET_NAME_COOKIE_GROUP_COMFORT_FEATURES = 'cookie.groupComfortFeatures';
    final public const SNIPPET_NAME_COOKIE_GROUP_MARKETING = 'cookie.groupMarketing';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        array $sessionOptions = [],
        private readonly ?CookieProviderInterface $legacyCookieProvider = null,
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieGroupCollection
    {
        $cookieGroups = new CookieGroupCollection();

        if ($this->legacyCookieProvider && !Feature::isActive('v6.8.0.0')) {
            // @deprecated tag:v6.8.0 - Legacy cookie conversion can be removed completely
            $this->convertLegacyCookies($cookieGroups, $this->legacyCookieProvider->getCookieGroups());
        } else {
            $cookieGroups->add($this->getCookieGroupRequiredEntries());
            $cookieGroups->add($this->getCookieGroupStatistical());
            $cookieGroups->add($this->getCookieGroupComfortFeatures());
            $cookieGroups->add($this->getCookieGroupMarketing());
        }

        $this->eventDispatcher->dispatch(new CookieGroupCollectEvent($cookieGroups, $request, $salesChannelContext));

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

    /**
     * @param list<CookieGroupArray> $legacyCookieGroups
     */
    private function convertLegacyCookies(CookieGroupCollection $cookieGroupCollection, array $legacyCookieGroups): void
    {
        foreach ($legacyCookieGroups as $legacyCookieGroup) {
            $snippetName = $legacyCookieGroup['snippet_name'] ?? null;
            if ($snippetName === null) {
                throw CookieException::invalidLegacyCookieGroupProvided($legacyCookieGroup);
            }
            $snippetName = (string) $snippetName;

            $cookieGroup = $cookieGroupCollection->get($snippetName);
            if ($cookieGroup === null) {
                $cookieGroup = new CookieGroup($snippetName);
                $cookieGroupCollection->add($cookieGroup);
            }

            if (\array_key_exists('snippet_description', $legacyCookieGroup)) {
                $description = (string) $legacyCookieGroup['snippet_description'];
                $cookieGroup->description = $description !== '' ? $description : null;
            }

            if (\array_key_exists('cookie', $legacyCookieGroup)) {
                $cookie = (string) $legacyCookieGroup['cookie'];
                $cookieGroup->setCookie($cookie !== '' ? $cookie : null);
            }

            if (\array_key_exists('value', $legacyCookieGroup)) {
                $value = (string) $legacyCookieGroup['value'];
                $cookieGroup->value = $value !== '' ? $value : null;
            }

            if (isset($legacyCookieGroup['expiration'])) {
                $cookieGroup->expiration = (int) $legacyCookieGroup['expiration'];
            }

            if (isset($legacyCookieGroup['isRequired'])) {
                $cookieGroup->isRequired = (bool) $legacyCookieGroup['isRequired'];
            }

            if (\array_key_exists('entries', $legacyCookieGroup)) {
                $cookieEntries = $cookieGroup->getEntries();
                if ($cookieEntries === null) {
                    $cookieEntries = new CookieEntryCollection();
                    $cookieGroup->setEntries($cookieEntries);
                }

                foreach ($legacyCookieGroup['entries'] as $entry) {
                    $cookie = $entry['cookie'] ?? null;
                    if ($cookie === null) {
                        throw CookieException::invalidLegacyCookieEntryProvided($entry);
                    }
                    $cookieEntry = new CookieEntry((string) $cookie);

                    if (\array_key_exists('snippet_name', $entry)) {
                        $name = (string) $entry['snippet_name'];
                        $cookieEntry->name = $name !== '' ? $name : null;
                    }

                    if (\array_key_exists('snippet_description', $entry)) {
                        $description = (string) $entry['snippet_description'];
                        $cookieEntry->description = $description !== '' ? $description : null;
                    }

                    if (\array_key_exists('value', $entry)) {
                        $value = (string) $entry['value'];
                        $cookieEntry->value = $value !== '' ? $value : null;
                    }

                    if (isset($entry['expiration'])) {
                        $cookieEntry->expiration = (int) $entry['expiration'];
                    }

                    if (isset($entry['hidden'])) {
                        $cookieEntry->hidden = (bool) $entry['hidden'];
                    }

                    $cookieEntries->add($cookieEntry);
                }
            }
        }
    }
}
