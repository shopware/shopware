<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @phpstan-import-type Cookie from AppEntity
 */
#[Package('framework')]
class AppCookieCollectListener
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new NotEqualsFilter('app.cookies', null)
        );

        $apps = $this->appRepository->search($criteria, $event->getContext())->getEntities();

        foreach ($apps as $app) {
            $this->addCookies($event->cookieGroupCollection, $app->getCookies());
        }
    }

    /**
     * @param list<Cookie> $appCookies
     */
    private function addCookies(CookieGroupCollection $cookieGroupCollection, array $appCookies): void
    {
        foreach ($appCookies as $cookie) {
            $originalGroupName = $cookie['snippet_name'];
            $targetGroupName = $this->determineTargetGroup($originalGroupName, $cookie);
            $isRedirected = $targetGroupName !== $originalGroupName;

            $cookieGroup = $this->getOrCreateCookieGroup($cookieGroupCollection, $targetGroupName);

            if (\array_key_exists('snippet_description', $cookie)) {
                $cookieGroup->description = $cookie['snippet_description'];
            }

            if ($isRedirected) {
                $this->addRedirectedCookie($cookieGroup, $cookie);
            } else {
                $this->addNonRedirectedCookie($cookieGroup, $cookie);
            }
        }
    }

    private function getOrCreateCookieGroup(CookieGroupCollection $collection, string $groupName): CookieGroup
    {
        $cookieGroup = $collection->get($groupName);
        if ($cookieGroup === null) {
            $cookieGroup = new CookieGroup($groupName);
            $collection->add($cookieGroup);
        }

        return $cookieGroup;
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function addNonRedirectedCookie(CookieGroup $cookieGroup, array $cookie): void
    {
        // Original behavior: app creates its own group
        if ($this->isSingleCookie($cookie)) {
            $this->setSingleCookieOnGroup($cookieGroup, $cookie);
        }

        if (\array_key_exists('entries', $cookie)) {
            $this->addCookieEntries($cookieGroup, $cookie['entries']);
        }
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function addRedirectedCookie(CookieGroup $cookieGroup, array $cookie): void
    {
        // Redirected: Always use entries collection to support merging multiple apps
        $entries = $this->ensureEntriesCollection($cookieGroup);

        if ($this->isSingleCookie($cookie)) {
            $entries->add($this->createCookieEntryFromSingleCookie($cookie));
        }

        if (\array_key_exists('entries', $cookie)) {
            $this->addCookieEntriesToCollection($entries, $cookie['entries']);
        }
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function isSingleCookie(array $cookie): bool
    {
        return \array_key_exists('cookie', $cookie) && !\array_key_exists('entries', $cookie);
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function setSingleCookieOnGroup(CookieGroup $cookieGroup, array $cookie): void
    {
        $cookieGroup->setCookie($cookie['cookie']);

        if (\array_key_exists('value', $cookie)) {
            $cookieGroup->value = $cookie['value'];
        }

        if (\array_key_exists('expiration', $cookie)) {
            $cookieGroup->expiration = (int) $cookie['expiration'];
        }
    }

    private function ensureEntriesCollection(CookieGroup $cookieGroup): CookieEntryCollection
    {
        $entries = $cookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $cookieGroup->setEntries($entries);
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $cookie
     */
    private function createCookieEntryFromSingleCookie(array $cookie): CookieEntry
    {
        $entry = new CookieEntry($cookie['cookie']);
        $entry->name = $cookie['snippet_name'];

        if (\array_key_exists('snippet_description', $cookie)) {
            $entry->description = $cookie['snippet_description'];
        }

        if (\array_key_exists('value', $cookie)) {
            $entry->value = $cookie['value'];
        }

        if (\array_key_exists('expiration', $cookie)) {
            $entry->expiration = (int) $cookie['expiration'];
        }

        return $entry;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function addCookieEntries(CookieGroup $cookieGroup, array $entries): void
    {
        $collection = $this->ensureEntriesCollection($cookieGroup);
        $this->addCookieEntriesToCollection($collection, $entries);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function addCookieEntriesToCollection(CookieEntryCollection $collection, array $entries): void
    {
        foreach ($entries as $entryData) {
            $collection->add($this->createCookieEntry($entryData));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCookieEntry(array $data): CookieEntry
    {
        $entry = new CookieEntry($data['cookie']);

        if (\array_key_exists('snippet_name', $data)) {
            $entry->name = $data['snippet_name'];
        }

        if (\array_key_exists('snippet_description', $data)) {
            $entry->description = $data['snippet_description'];
        }

        if (\array_key_exists('value', $data)) {
            $entry->value = $data['value'];
        }

        if (\array_key_exists('expiration', $data)) {
            $entry->expiration = (int) $data['expiration'];
        }

        return $entry;
    }

    /**
     * Determine the target group based on manifest configuration:
     * 1. target_group attribute on individual group (highest priority)
     * 2. default-target-group attribute on cookies element
     * 3. Use original group name (backward compatible, lowest priority)
     *
     * @param array<string, mixed> $cookie
     *
     * @return string The target group name
     */
    private function determineTargetGroup(string $originalGroupName, array $cookie): string
    {
        // Priority 1: target_group from manifest (per-group override)
        if (\array_key_exists('target_group', $cookie)) {
            return $cookie['target_group'];
        }

        // Priority 2: default_target_group from manifest (app-level default)
        if (\array_key_exists('default_target_group', $cookie)) {
            return $cookie['default_target_group'];
        }

        // Priority 3: Use original group name (backward compatible)
        return $originalGroupName;
    }
}
