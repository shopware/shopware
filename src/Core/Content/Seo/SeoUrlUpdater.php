<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * This class can be used to regenerate the seo urls for a route and an offset at ids.
 */
class SeoUrlUpdater
{
    /**
     * @var EntityRepositoryInterface
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
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        SeoUrlGenerator $seoUrlGenerator,
        SeoUrlPersister $seoUrlPersister,
        Connection $connection,
        EntityRepositoryInterface $salesChannelRepository
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
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });
        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());

        $salesChannels = $this->fetchSalesChannels();

        $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);
        if (!$route) {
            throw new \RuntimeException(sprintf('Route by name %s not found', $routeName));
        }

        foreach ($templates as $config) {
            $salesChannelId = $config['salesChannelId'];
            $languageId = $config['languageId'];
            $template = $config['template'];

            $chain = $languageChains[$languageId];
            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $context->setConsiderInheritance(true);

            $salesChannel = $salesChannels->get($salesChannelId);

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids, $urls);
        }
    }

    private function loadTemplates(array $routes): array
    {
        $domains = $this->connection->fetchAll(
            'SELECT
               LOWER(HEX(sales_channel.id)) as salesChannelId,
               LOWER(HEX(domains.language_id)) as languageId
             FROM sales_channel_domain as domains
             INNER JOIN sales_channel
               ON domains.sales_channel_id = sales_channel.id
               AND sales_channel.active = 1'
        );

        if ($routes === []) {
            return [];
        }

        $modified = $this->connection->fetchAll(
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

        if (!array_key_exists('', $grouped)) {
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

    private function fetchSalesChannels(): EntityCollection
    {
        $context = Context::createDefaultContext();

        return $context->disableCache(function (Context $context) {
            return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
        });
    }

    private function fetchLanguageChains(array $languages): array
    {
        $languageChains = [];
        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $languageId = $language->getId();
            $languageChains[$languageId] = $this->getLanguageIdChain($languageId);
        }

        return $languageChains;
    }

    private function getLanguageIdChain(string $languageId): array
    {
        return [
            $languageId,
            $this->getParentLanguageId($languageId),
            Defaults::LANGUAGE_SYSTEM,
        ];
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        // TODO: optimize to one query
        $result = $this->connection
            ->executeQuery('SELECT LOWER(HEX(parent_id)) FROM language WHERE id = :id', ['id' => $languageId])
            ->fetchColumn();

        return $result ? (string) $result : null;
    }
}
