<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CookieService
{
    /**
     * @var CookieProvider
     */
    private $cookieProvider;

    public function __construct(CookieProvider $cookieProvider)
    {
        $this->cookieProvider = $cookieProvider;
    }

    public function getCookieGroups(SalesChannelContext $context)
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();

        return $this->filterGoogleAnalyticsCookie($context, $cookieGroups);
    }

    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, array $cookieGroups): array
    {
        if ($context->getSalesChannel()->getAnalytics() && $context->getSalesChannel()->getAnalytics()->isActive()) {
            return $cookieGroups;
        }

        $filteredGroups = [];

        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupStatistical') {
                $cookieGroup['entries'] = array_filter($cookieGroup['entries'], function ($item) {
                    return $item['snippet_name'] !== 'cookie.groupStatisticalGoogleAnalytics';
                });
                // Only add statistics cookie group if it has entries
                if (count($cookieGroup['entries']) > 0) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }
            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }
}
