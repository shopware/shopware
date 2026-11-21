<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\LegacyCookieProviderBridgePass;

/**
 * @internal
 *
 * Keeps extensions working that still decorate the deprecated {@see CookieProviderInterface}.
 * The Core cookie provider only knows the {@see CookieGroupCollectEvent}.
 * This listener runs the legacy decorator chain, compares its result with the undecorated default provider,
 * and applies only the difference to the collected cookie groups.
 * Shops without such decorators never register this listener, see {@see LegacyCookieProviderBridgePass}.
 *
 * Remove together with {@see CookieProviderInterface} in v6.9.0.0.
 *
 * @phpstan-import-type CookieGroupArray from CookieProviderInterface
 */
#[Package('discovery')]
class LegacyCookieProviderBridge
{
    /**
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly CookieProviderInterface $legacyCookieProvider,
        private readonly array $sessionOptions = [],
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        Feature::silent('v6.9.0.0', function () use ($event): void {
            $defaults = $this->convert((new CookieProvider($this->sessionOptions))->getCookieGroups());
            $decorated = $this->convert($this->legacyCookieProvider->getCookieGroups());

            $this->applyDifference($event->cookieGroupCollection, $defaults, $decorated);
        });
    }

    private function applyDifference(CookieGroupCollection $target, CookieGroupCollection $defaults, CookieGroupCollection $decorated): void
    {
        foreach ($defaults as $technicalName => $defaultGroup) {
            if (!$decorated->has($technicalName)) {
                $target->remove($technicalName);
            }
        }

        foreach ($decorated as $technicalName => $decoratedGroup) {
            $defaultGroup = $defaults->get($technicalName);
            $targetGroup = $target->get($technicalName);

            if ($targetGroup === null) {
                // A default group missing in the target was removed by an earlier listener, keep it removed.
                if ($defaultGroup === null) {
                    $target->add($decoratedGroup);
                }

                continue;
            }

            $this->applyGroupDifference($targetGroup, $defaultGroup ?? new CookieGroup($technicalName), $decoratedGroup);
        }
    }

    private function applyGroupDifference(CookieGroup $target, CookieGroup $default, CookieGroup $decorated): void
    {
        if ($this->hasChanged($default, $decorated, 'isRequired')) {
            $target->isRequired = $decorated->isRequired;
        }

        if ($this->hasChanged($default, $decorated, 'description')) {
            $target->description = $decorated->description;
        }

        if ($this->hasChanged($default, $decorated, 'value')) {
            $target->value = $decorated->value;
        }

        if ($this->hasChanged($default, $decorated, 'expiration')) {
            $target->expiration = $decorated->expiration;
        }

        $this->applyEntriesDifference($target, $default, $decorated);
        $this->applyCookieDifference($target, $default, $decorated);
    }

    /**
     * A group holds either entries or a standalone cookie.
     * A legacy group that became a standalone cookie replaces the entries of the target only when none are left,
     * so entries added by other listeners are never dropped.
     */
    private function applyCookieDifference(CookieGroup $target, CookieGroup $default, CookieGroup $decorated): void
    {
        $cookie = $decorated->getCookie();
        if ($cookie === $default->getCookie()) {
            return;
        }

        $targetEntries = $target->getEntries();
        if ($targetEntries !== null) {
            if ($targetEntries->count() > 0) {
                return;
            }

            $target->setEntries(null);
        }

        $target->setCookie($cookie);
    }

    private function applyEntriesDifference(CookieGroup $target, CookieGroup $default, CookieGroup $decorated): void
    {
        $defaultEntries = $default->getEntries() ?? new CookieEntryCollection();
        $decoratedEntries = $decorated->getEntries() ?? new CookieEntryCollection();
        if ($defaultEntries->count() === 0 && $decoratedEntries->count() === 0) {
            return;
        }

        $targetEntries = $target->getEntries();
        if ($targetEntries === null) {
            if ($target->getCookie() !== null) {
                return;
            }

            $targetEntries = new CookieEntryCollection();
            $target->setEntries($targetEntries);
        }

        foreach ($defaultEntries as $cookie => $defaultEntry) {
            if (!$decoratedEntries->has($cookie)) {
                $targetEntries->remove($cookie);
            }
        }

        foreach ($decoratedEntries as $cookie => $decoratedEntry) {
            $defaultEntry = $defaultEntries->get($cookie);
            if ($defaultEntry !== null && get_object_vars($defaultEntry) === get_object_vars($decoratedEntry)) {
                continue;
            }

            $targetEntries->add($decoratedEntry);
        }
    }

    /**
     * Uninitialized properties are not part of {@see get_object_vars()}, so a property the legacy array did not set never counts as a change.
     */
    private function hasChanged(CookieGroup $default, CookieGroup $decorated, string $property): bool
    {
        $decoratedProperties = get_object_vars($decorated);
        if (!\array_key_exists($property, $decoratedProperties)) {
            return false;
        }

        $defaultProperties = get_object_vars($default);

        return !\array_key_exists($property, $defaultProperties) || $defaultProperties[$property] !== $decoratedProperties[$property];
    }

    /**
     * @param list<CookieGroupArray> $legacyCookieGroups
     */
    private function convert(array $legacyCookieGroups): CookieGroupCollection
    {
        $cookieGroupCollection = new CookieGroupCollection();

        foreach ($legacyCookieGroups as $legacyCookieGroup) {
            $snippetName = $legacyCookieGroup['snippet_name'] ?? null;
            if (!\is_string($snippetName)) {
                throw CookieException::invalidLegacyCookieGroupProvided($legacyCookieGroup);
            }

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

            if (!\array_key_exists('entries', $legacyCookieGroup)) {
                continue;
            }

            $cookieEntries = $cookieGroup->getEntries();
            if ($cookieEntries === null) {
                $cookieEntries = new CookieEntryCollection();
                $cookieGroup->setEntries($cookieEntries);
            }

            foreach ($legacyCookieGroup['entries'] as $entry) {
                $cookieEntries->add($this->convertEntry($entry));
            }
        }

        return $cookieGroupCollection;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function convertEntry(array $entry): CookieEntry
    {
        $cookie = $entry['cookie'] ?? null;
        if (!\is_string($cookie)) {
            throw CookieException::invalidLegacyCookieEntryProvided($entry);
        }

        $cookieEntry = new CookieEntry($cookie);

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

        return $cookieEntry;
    }
}
