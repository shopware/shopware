<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class CookieService
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     */
    public function __construct(
        private EntityRepository $salesChannelAnalyticsRepository,
        private TranslatorInterface $translator
    ) {
    }

    public function filterCookieGroups(CookieGroupCollection $cookieGroups, SalesChannelContext $context): CookieGroupCollection
    {
        return $this->filterGoogleAnalyticsCookie($context, $cookieGroups);
    }

    /**
     * Translates the snippet names and descriptions of cookie groups and their entries.
     */
    public function translateCookieGroups(CookieGroupCollection $cookieGroups): void
    {
        foreach ($cookieGroups as $group) {
            $group->snippetKeyName = $this->translator->trans($group->snippetKeyName);

            if (isset($group->snippetKeyDescription)) {
                $group->snippetKeyDescription = $this->translator->trans($group->snippetKeyDescription);
            }

            $entries = $group->getEntries();
            if ($entries !== null) {
                foreach ($entries as $entry) {
                    if (isset($entry->snippetKeyName)) {
                        $entry->snippetKeyName = $this->translator->trans($entry->snippetKeyName);
                    }

                    if (isset($entry->snippetKeyDescription)) {
                        $entry->snippetKeyDescription = $this->translator->trans($entry->snippetKeyDescription);
                    }
                }
            }
        }
    }

    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, CookieGroupCollection $cookieGroups): CookieGroupCollection
    {
        $salesChannel = $context->getSalesChannel();

        if ($salesChannel->getAnalytics() === null && $salesChannel->getAnalyticsId() !== null) {
            $criteria = new Criteria([$salesChannel->getAnalyticsId()]);
            $criteria->setTitle('cookie-controller::load-analytics');

            $salesChannel->setAnalytics(
                $this->salesChannelAnalyticsRepository->search($criteria, $context->getContext())->getEntities()->first()
            );
        }

        if ($salesChannel->getAnalytics()?->isActive()) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup->snippetKeyName === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL) {
                $cookieGroup = $this->filterCookieGroup('google-analytics-enabled', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            if ($cookieGroup->snippetKeyName === CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING) {
                $cookieGroup = $this->filterCookieGroup('google-ads-enabled', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return new CookieGroupCollection($filteredGroups);
    }

    private function filterCookieGroup(string $cookieName, CookieGroup $cookieGroup): ?CookieGroup
    {
        $entries = $cookieGroup->getEntries();
        if (!$entries) {
            return null;
        }

        $cookieGroup->setEntries($entries->filter(function (CookieEntry $item) use ($cookieName) {
            return $item->cookie !== $cookieName;
        }));

        if (!$cookieGroup->getEntries() || \count($cookieGroup->getEntries()) === 0) {
            return null;
        }

        return $cookieGroup;
    }
}
