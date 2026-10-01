<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\App;

use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlSynchronizer
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly AppFeatureStorage $storage,
        private readonly EntityRepository $salesChannelRepository,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly LanguageLocaleCodeProvider $languageLocaleProvider,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function syncStaticRoutes(?string $appId = null): void
    {
        $seoUrls = $this->fetchSeoUrls($appId);

        if ($seoUrls === []) {
            return;
        }

        $pathInfos = [];
        foreach ($seoUrls as $seoUrl) {
            $pathInfos[$seoUrl->getRouteName()] = $this->generatePathInfo($seoUrl);
        }

        foreach ($this->fetchSalesChannels() as $salesChannel) {
            foreach ($this->languageChains($salesChannel) as $languageChain) {
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $languageChain);

                foreach ($seoUrls as $seoUrl) {
                    $this->write($seoUrl, $pathInfos[$seoUrl->getRouteName()], $salesChannel, $context);
                }
            }
        }
    }

    /**
     * @return list<AppSeoUrlConfig>
     */
    private function fetchSeoUrls(?string $appId): array
    {
        $seoUrls = [];

        foreach ($this->storage->forActiveApps(AppSeoUrlConfig::class) as $feature) {
            if ($appId === null || $feature->appId === $appId) {
                $seoUrls[] = $feature->config;
            }
        }

        return $seoUrls;
    }

    private function write(AppSeoUrlConfig $seoUrl, string $pathInfo, SalesChannelEntity $salesChannel, Context $context): void
    {
        $foreignKey = Uuid::fromStringToHex($seoUrl->getRouteName());

        $this->seoUrlPersister->forceUpdateSeoUrls(
            $context,
            $seoUrl->getRouteName(),
            [$foreignKey],
            [[
                'foreignKey' => $foreignKey,
                'pathInfo' => $pathInfo,
                'seoPathInfo' => $this->resolvePath($seoUrl, $context),
                'salesChannelId' => $salesChannel->getId(),
                'isCanonical' => true,
                'isModified' => true,
                'isDeleted' => false,
            ]],
            $salesChannel
        );
    }

    private function generatePathInfo(AppSeoUrlConfig $seoUrl): string
    {
        $pathInfo = $this->router->generate(AppSeoUrlRoute::TARGET_ROUTE, ['hook' => $seoUrl->getHook()]);
        $basePath = $this->requestStack->getMainRequest()?->getBasePath() ?? '';

        if ($basePath === '' || !str_starts_with($pathInfo, $basePath)) {
            return $pathInfo;
        }

        return substr($pathInfo, \strlen($basePath));
    }

    private function resolvePath(AppSeoUrlConfig $seoUrl, Context $context): string
    {
        $paths = $seoUrl->getPaths();

        foreach ($context->getLanguageIdChain() as $languageId) {
            $path = $paths[$this->languageLocaleProvider->getLocaleForLanguageId($languageId)] ?? null;

            if ($path !== null) {
                return $path;
            }
        }

        return (string) array_first($paths);
    }

    private function fetchSalesChannels(): SalesChannelCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('app-seo-url::static-sync');
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_API),
        ]));
        $criteria->addAssociation('domains.language');

        return $this->salesChannelRepository->search($criteria, Context::createDefaultContext())->getEntities();
    }

    /**
     * @return list<non-empty-list<string>>
     */
    private function languageChains(SalesChannelEntity $salesChannel): array
    {
        $languageChains = [];

        foreach ($salesChannel->getDomains() ?? [] as $domain) {
            $languageId = $domain->getLanguageId();

            if (isset($languageChains[$languageId])) {
                continue;
            }

            $parentId = $domain->getLanguage()?->getParentId();

            $languageChains[$languageId] = array_values(array_unique(
                $parentId === null
                    ? [$languageId, Defaults::LANGUAGE_SYSTEM]
                    : [$languageId, $parentId, Defaults::LANGUAGE_SYSTEM]
            ));
        }

        return array_values($languageChains);
    }
}
