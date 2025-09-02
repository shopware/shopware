<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class CookieService
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    public function removeCookieGroupsWithoutCookies(CookieGroupCollection $cookieGroups): void
    {
        foreach ($cookieGroups as $cookieGroup) {
            // If the group is a cookie itself, it cannot have cookie entries but needs to be kept
            if ($cookieGroup->getCookie() !== null) {
                continue;
            }

            $entries = $cookieGroup->getEntries();
            if ($entries === null || $entries->count() === 0) {
                // Cookie groups without cookie entries should not be shown to the user
                $cookieGroups->remove($cookieGroup->snippetKeyName);
            }
        }
    }

    /**
     * Translates the snippet names and descriptions of cookie groups and their entries.
     */
    public function translateCookieGroups(CookieGroupCollection $cookieGroups): void
    {
        foreach ($cookieGroups as $group) {
            $group->translatedName = $this->translator->trans($group->snippetKeyName);

            if (isset($group->snippetKeyDescription)) {
                $group->translatedDescription = $this->translator->trans($group->snippetKeyDescription);
            }

            $entries = $group->getEntries();
            if ($entries !== null) {
                foreach ($entries as $entry) {
                    if (isset($entry->snippetKeyName)) {
                        $entry->translatedName = $this->translator->trans($entry->snippetKeyName);
                    }

                    if (isset($entry->snippetKeyDescription)) {
                        $entry->translatedDescription = $this->translator->trans($entry->snippetKeyDescription);
                    }
                }
            }
        }
    }
}
