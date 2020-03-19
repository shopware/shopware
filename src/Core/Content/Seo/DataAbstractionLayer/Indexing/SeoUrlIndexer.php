<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Content\Seo\SeoUrlUpdater instead
 */
class SeoUrlIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlGenerator;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    /**
     * @var SeoUrlPersister
     */
    private $seoUrlPersister;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        SeoUrlGenerator $seoUrlGenerator,
        SeoUrlPersister $seoUrlPersister,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        EntityRepositoryInterface $languageRepository,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $salesChannelRepository,
        MessageBusInterface $bus
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
        $this->seoUrlPersister = $seoUrlPersister;
        $this->languageRepository = $languageRepository;
        $this->iteratorFactory = $iteratorFactory;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->bus = $bus;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });

        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());
        $salesChannels = $this->fetchSalesChannels();

        $routes = array_keys($this->seoUrlRouteRegistry->getSeoUrlRoutes());

        $templates = $this->loadTemplates($routes);

        foreach ($templates as $config) {
            $salesChannelId = $config['salesChannelId'];
            $languageId = $config['languageId'];
            $routeName = $config['route'];
            $template = $config['template'];

            $salesChannel = $salesChannels->get($salesChannelId);

            $language = $languages->get($languageId);

            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $languageChains[$languageId]);
            $context->setConsiderInheritance(true);

            $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);

            if ($route->getConfig()->supportsNewIndexer()) {
                continue;
            }

            $iterator = $this->iteratorFactory->createIterator($route->getConfig()->getDefinition());

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent(
                    sprintf('Start indexing %s seo urls for language %s of sales channel %s', $routeName, $language->getName(), $salesChannel->getName()),
                    $iterator->fetchCount()
                ),
                ProgressStartedEvent::NAME
            );

            while ($ids = $iterator->fetch()) {
                $seoUrls = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);

                $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids, $seoUrls);

                $this->eventDispatcher->dispatch(new ProgressAdvancedEvent(\count($ids)), ProgressAdvancedEvent::NAME);
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent(sprintf('Finished indexing %s seo urls for language %s for sales channel %s', $routeName, $language->getName(), $salesChannel->getName())),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });

        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());
        $salesChannels = $this->fetchSalesChannels();

        $dataOffset = null;
        $routeOffset = 0;

        if ($lastId) {
            $dataOffset = $lastId['dataOffset'];
            $routeOffset = $lastId['routeOffset'];
        }

        $routes = array_values($this->seoUrlRouteRegistry->getSeoUrlRoutes());

        if (!isset($routes[$routeOffset])) {
            return null;
        }

        /** @var SeoUrlRouteInterface $route */
        $route = $routes[$routeOffset];

        if ($route->getConfig()->supportsNewIndexer()) {
            ++$routeOffset;

            return [
                'dataOffset' => null,
                'routeOffset' => $routeOffset,
            ];
        }
        $templates = $this->loadTemplates([$route->getConfig()->getRouteName()]);

        $iterator = $this->iteratorFactory->createIterator($route->getConfig()->getDefinition(), $dataOffset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            ++$routeOffset;

            return [
                'dataOffset' => null,
                'routeOffset' => $routeOffset,
            ];
        }

        foreach ($templates as $config) {
            $salesChannelId = $config['salesChannelId'];
            $languageId = $config['languageId'];
            $routeName = $config['route'];
            $template = $config['template'];

            $salesChannel = $salesChannels->get($salesChannelId);

            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $languageChains[$languageId]);
            $context->setConsiderInheritance(true);

            $seoUrls = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $salesChannel);

            $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids, $seoUrls);
        }

        return [
            'dataOffset' => $iterator->getOffset(),
            'routeOffset' => $routeOffset,
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        /** @var SeoUrlExtractIdResult[] $updates */
        $updates = [];
        $total = 0;
        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            if ($seoUrlRoute->getConfig()->supportsNewIndexer()) {
                continue;
            }
            $extractResult = $seoUrlRoute->extractIdsToUpdate($event);

            $total += count($extractResult->getIds());

            if ($extractResult->mustReindex()) {
                // if we need to reindex completely, create task for that and finish immediately
                $message = new IndexerMessage([self::getName()]);
                $message->setTimestamp(new \DateTime());
                $this->bus->dispatch($message);

                return;
            }

            if (empty($extractResult->getIds())) {
                continue;
            }

            $updates[$seoUrlRoute->getConfig()->getRouteName()] = $extractResult;
        }

        if ($total > 50) {
            // if we need to reindex completely, create task for that and finish immediately
            $message = new IndexerMessage([self::getName()]);
            $message->setTimestamp(new \DateTime());
            $this->bus->dispatch($message);

            return;
        }

        if (empty($updates)) {
            return;
        }

        $templates = $this->loadTemplates(array_keys($updates));
        if (empty($templates)) {
            return;
        }

        $context = Context::createDefaultContext();
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });
        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());

        $salesChannels = $this->fetchSalesChannels();

        foreach ($templates as $config) {
            $salesChannelId = $config['salesChannelId'];
            $languageId = $config['languageId'];
            $routeName = $config['route'];
            $template = $config['template'];

            $ids = $updates[$routeName];
            if (empty($ids)) {
                continue;
            }

            $route = $this->seoUrlRouteRegistry->findByRouteName($routeName);

            $chain = $languageChains[$languageId];
            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $context->setConsiderInheritance(true);

            $salesChannel = $salesChannels->get($salesChannelId);

            // generate new seo urls
            $urls = $this->seoUrlGenerator->generate($ids->getIds(), $template, $route, $context, $salesChannel);

            // persist seo urls to storage
            $this->seoUrlPersister->updateSeoUrls($context, $routeName, $ids->getIds(), $urls);
        }
    }

    public static function getName(): string
    {
        return 'Swag.SeoUrlIndexer';
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

    private function fetchSalesChannels(): EntityCollection
    {
        $context = Context::createDefaultContext();
        $entities = $context->disableCache(function (Context $context) {
            return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
        });

        return $entities;
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

        $modified = $this->connection->fetchAll(
            'SELECT LOWER(HEX(sales_channel_id)) as sales_channel_id, route_name, template
             FROM seo_url_template
             WHERE route_name IN (:routes)',
            ['routes' => $routes],
            ['routes' => Connection::PARAM_STR_ARRAY]
        );

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
}
