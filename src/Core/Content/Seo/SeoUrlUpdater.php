<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * This class can be used to regenerate the seo urls for a route and an offset at ids.
 */
#[Package('sales-channel')]
class SeoUrlUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly Connection $connection,
        private readonly EntityRepository $salesChannelRepository
    ) {
    }

    /**
     * @param list<string> $ids
     */
    public function update(string $routeName, array $ids): void
    {
        $templates = $this->loadTemplates([$routeName]);
        if (empty($templates)) {
            return;
        }

        $context = Context::createDefaultContext();
        /** @var list<LanguageEntity> $languages */
        $languages = $this->languageRepository->search(new Criteria(), $context)->getEntities()->getElements();

        $languageChains = $this->fetchLanguageChains($languages);

        $salesChannels = $this->fetchSalesChannels();

        $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);
        if (!$route) {
            throw new \RuntimeException(sprintf('Route by name %s not found', $routeName));
        }

        foreach ($templates as $config) {
            $salesChannelId = $config['salesChannelId'];
            $languageId = $config['languageId'];
            $template = $config['template'] ?? '';

            if ($template === '') {
                continue;
            }

            $chain = $languageChains[$languageId];
            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $context->setConsiderInheritance(true);

            $salesChannel = $salesChannels->get($salesChannelId);

            if ($salesChannel === null) {
                continue;
            }

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids, $urls, $salesChannel);
        }
    }

    /**
     * @param list<string> $routes
     *
     * @return list<array{salesChannelId: string, languageId: string, route: string, template: string|null}>
     */
    private function loadTemplates(array $routes): array
    {
        $domains = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT
               LOWER(HEX(sales_channel.id)) as salesChannelId,
               LOWER(HEX(domains.language_id)) as languageId
             FROM sales_channel_domain as domains
             INNER JOIN sales_channel
               ON domains.sales_channel_id = sales_channel.id
               AND sales_channel.active = 1'
        );

        if ($routes === [] || $domains === []) {
            return [];
        }

        $modified = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, route_name, template
             FROM seo_url_template
             WHERE route_name IN (:routes)',
            ['routes' => $routes],
            ['routes' => ArrayParameterType::STRING]
        );

        if ($modified === []) {
            return [];
        }

        $grouped = [];
        foreach ($modified as $template) {
            $grouped[$template['sales_channel_id']][$template['route_name']] = $template['template'];
        }

        if (!\array_key_exists('', $grouped)) {
            throw new \RuntimeException('Default templates not configured');
        }
        $defaults = $grouped[''];

        $result = [];
        foreach ($domains as $domain) {
            $salesChannelId = $domain['salesChannelId'];

            foreach ($routes as $route) {
                $template = $defaults[$route];
                if (isset($grouped[$salesChannelId][$route])) {
                    $template = $grouped[$salesChannelId][$route];
                }

                $result[] = [
                    'salesChannelId' => $salesChannelId,
                    'languageId' => $domain['languageId'],
                    'route' => $route,
                    'template' => $template,
                ];
            }
        }

        return $result;
    }

    private function fetchSalesChannels(): SalesChannelCollection
    {
        $context = Context::createDefaultContext();

        /** @var SalesChannelCollection $entities */
        $entities = $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();

        return $entities;
    }

    /**
     * @param list<LanguageEntity> $languages
     *
     * @return array<string, list<string>>
     */
    private function fetchLanguageChains(array $languages): array
    {
        $languageChains = [];
        foreach ($languages as $language) {
            $languageId = $language->getId();
            $languageChains[$languageId] = array_filter([
                $languageId,
                $language->getParentId(),
                Defaults::LANGUAGE_SYSTEM,
            ]);
        }

        return $languageChains;
    }
}
