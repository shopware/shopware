<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * This class can be used to regenerate the seo urls for a route and an offset at ids.
 */
class SeoUrlUpdater
{
    /**
     * @var EntityRepository
     */
    private $languageRepository;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlGenerator;

    /**
     * @var SeoUrlPersister
     */
    private $seoUrlPersister;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $languageRepository,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        SeoUrlGenerator $seoUrlGenerator,
        SeoUrlPersister $seoUrlPersister,
        Connection $connection,
        EntityRepository $salesChannelRepository
    ) {
        $this->languageRepository = $languageRepository;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->seoUrlPersister = $seoUrlPersister;
        $this->connection = $connection;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function update(string $routeName, array $ids): void
    {
        $templates = $this->loadTemplates([$routeName]);
        if (empty($templates)) {
            return;
        }

        $context = Context::createDefaultContext();
        $languages = $this->languageRepository->search(new Criteria(), $context);

        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());

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

            if ($salesChannel === null && Feature::isActive('FEATURE_NEXT_13410')) {
                continue;
            }

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids, $urls, $salesChannel);
        }
    }

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
            ['routes' => Connection::PARAM_STR_ARRAY]
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

    private function fetchLanguageChains(array $languages): array
    {
        $languageChains = [];
        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $languageId = $language->getId();
            $languageChains[$languageId] = [
                $languageId,
                $language->getParentId(),
                Defaults::LANGUAGE_SYSTEM,
            ];
        }

        return $languageChains;
    }
}
