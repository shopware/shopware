<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('inventory')]
class AppStaticSeoUrlSynchronizer
{
    private const FALLBACK_LOCALE = 'en-GB';

    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly AppSeoUrlRouteLoader $routeLoader,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SeoUrlPersister $seoUrlPersister,
    ) {
    }

    public function sync(?string $appId = null): void
    {
        $routes = $this->routeLoader->getStaticRoutes($appId);

        if ($routes === []) {
            return;
        }

        foreach ($this->fetchSalesChannels() as $salesChannel) {
            foreach ($this->localesByLanguage($salesChannel) as $languageId => $localeCode) {
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId, Defaults::LANGUAGE_SYSTEM]);

                foreach ($routes as $route) {
                    $path = $this->resolvePath($route['paths'], $localeCode);
                    $foreignKey = Uuid::fromStringToHex($route['routeName']);

                    $this->seoUrlPersister->forceUpdateSeoUrls(
                        $context,
                        $route['routeName'],
                        [$foreignKey],
                        [[
                            'foreignKey' => $foreignKey,
                            'pathInfo' => AppSeoUrlRoute::PATH_PREFIX . $route['hook'],
                            'seoPathInfo' => $path,
                            'salesChannelId' => $salesChannel->getId(),
                            'isCanonical' => true,
                            'isModified' => true,
                            'isDeleted' => false,
                        ]],
                        $salesChannel
                    );
                }
            }
        }
    }

    private function fetchSalesChannels(): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url-routes::static-sync');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API),
        ]));
        $criteria->addAssociation('domains.language.locale');

        return $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    /**
     * @return array<string, string|null>
     */
    private function localesByLanguage(SalesChannelEntity $salesChannel): array
    {
        $locales = [];

        foreach ($salesChannel->getDomains() ?? [] as $domain) {
            $languageId = $domain->getLanguageId();

            if (\array_key_exists($languageId, $locales)) {
                continue;
            }

            $locales[$languageId] = $domain->getLanguage()?->getLocale()?->getCode();
        }

        return $locales;
    }

    /**
     * @param non-empty-array<string, string> $paths
     */
    private function resolvePath(array $paths, ?string $localeCode): string
    {
        if ($localeCode !== null && isset($paths[$localeCode])) {
            return $paths[$localeCode];
        }

        return $paths[self::FALLBACK_LOCALE] ?? array_first($paths);
    }
}
