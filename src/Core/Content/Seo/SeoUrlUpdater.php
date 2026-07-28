<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * This class can be used to regenerate the seo urls for a route and an offset at ids.
 */
#[Package('inventory')]
class SeoUrlUpdater
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param iterable<EntitySeoUrlRouteInterface> $entitySeoUrlRoutes
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly Connection $connection,
        private readonly EntityRepository $salesChannelRepository,
        private readonly iterable $entitySeoUrlRoutes = []
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function update(string $routeName, array $ids): void
    {
        if ($routeName === '') {
            return;
        }

        $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);

        if ($route !== null) {
            $templates = $this->loadUrlTemplate($routeName, false);
            if ($templates !== []) {
                $this->generateAndPersist($route, $routeName, $templates, $ids);
            }

            return;
        }

        // headless route (store-api): not registered as a full route, but configurable per headless sales channel
        $entityRoute = $this->findEntitySeoUrlRoute($routeName);
        if ($entityRoute === null) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        $templates = $this->loadUrlTemplate($routeName, true);
        if ($templates === []) {
            return;
        }

        $this->generateAndPersist(
            new ConfiguredEntitySeoUrlRoute($entityRoute),
            $routeName,
            $templates,
            $ids
        );
    }

    /**
     * @param list<string> $ids
     * @param list<array{salesChannelId: string, languageId: string, template: string}> $templates
     */
    private function generateAndPersist(SeoUrlRouteInterface $route, string $routeName, array $templates, array $ids): void
    {
        $context = Context::createDefaultContext();

        $languageChains = $this->fetchLanguageChains($context);

        $salesChannelIds = array_values(array_unique(array_column($templates, 'salesChannelId')));
        $criteria = new Criteria($salesChannelIds);
        $criteria->addAssociation('domains');
        $salesChannels = $this->salesChannelRepository->search($criteria, $context)->getEntities();

        foreach ($templates as $config) {
            $template = $config['template'];
            $salesChannel = $salesChannels->get($config['salesChannelId']);
            if ($template === '' || !$salesChannel) {
                continue;
            }

            $chain = $languageChains[$config['languageId']] ?? null;
            if (!$chain) {
                continue;
            }

            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $languageContext->setConsiderInheritance(true);

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $languageContext, $salesChannel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($languageContext, $routeName, $ids, $urls, $salesChannel);
        }
    }

    private function findEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        foreach ($this->entitySeoUrlRoutes as $entitySeoUrlRoute) {
            if ($entitySeoUrlRoute->getConfig()->getRouteName() === $routeName) {
                return $entitySeoUrlRoute;
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $routeName
     *
     * @return list<array{salesChannelId: string, languageId: string, template: string}>
     */
    private function loadUrlTemplate(string $routeName, bool $isHeadless): array
    {
        $query = 'SELECT DISTINCT
               LOWER(HEX(sales_channel.id)) as salesChannelId,
               LOWER(HEX(domains.language_id)) as languageId
             FROM sales_channel_domain as domains
             INNER JOIN sales_channel
               ON domains.sales_channel_id = sales_channel.id
               AND sales_channel.active = 1';

        $query .= $isHeadless
            ? ' AND sales_channel.type_id = :apiTypeId AND domains.is_external_storefront = 1'
            : ' AND sales_channel.type_id != :apiTypeId';
        $parameters = ['apiTypeId' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API)];

        $domains = $this->connection->fetchAllAssociative($query, $parameters);

        if ($domains === []) {
            return [];
        }

        $salesChannelTemplates = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, template
             FROM seo_url_template
             WHERE route_name LIKE :route
               AND is_headless = :isHeadless',
            ['route' => $routeName, 'isHeadless' => (int) $isHeadless]
        );

        $hasDefaultTemplate = \array_key_exists('', $salesChannelTemplates);

        if (!$isHeadless && !$hasDefaultTemplate) {
            throw SeoException::invalidTemplate('Default templates not configured');
        }

        $default = $hasDefaultTemplate ? (string) $salesChannelTemplates[''] : null;

        $result = [];
        foreach ($domains as $domain) {
            $template = $salesChannelTemplates[$domain['salesChannelId']] ?? $default;

            if ($template === null) {
                continue;
            }

            $result[] = [
                'salesChannelId' => $domain['salesChannelId'],
                'languageId' => $domain['languageId'],
                'template' => (string) $template,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, non-empty-list<string>>
     */
    private function fetchLanguageChains(Context $context): array
    {
        $languages = $this->languageRepository->search(new Criteria(), $context)->getEntities()->getElements();

        $languageChains = [];
        foreach ($languages as $language) {
            $languageId = $language->getId();
            $languageChains[$languageId] = array_values(array_filter([
                $languageId,
                $language->getParentId(),
                Defaults::LANGUAGE_SYSTEM,
            ]));
        }

        return $languageChains;
    }
}
