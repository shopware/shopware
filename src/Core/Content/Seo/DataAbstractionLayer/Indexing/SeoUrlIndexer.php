<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateLoader;
use Shopware\Core\Content\Seo\SeoUrlTemplate\TemplateGroup;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IndexerMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    /**
     * @var SeoUrlRouteRegistry
     */
    private $seoUrlRouteRegistry;

    /**
     * @var SeoUrlPersister
     */
    private $seoUrlPersister;

    /**
     * @var SeoUrlTemplateLoader
     */
    private $templateLoader;

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
        SeoUrlTemplateLoader $templateLoader,
        DefinitionInstanceRegistry $definitionRegistry,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        EntityRepositoryInterface $languageRepository,
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $salesChannelRepository,
        MessageBusInterface $bus
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->seoUrlGenerator = $seoUrlGenerator;
        $this->definitionRegistry = $definitionRegistry;
        $this->seoUrlRouteRegistry = $seoUrlRouteRegistry;
        $this->seoUrlPersister = $seoUrlPersister;
        $this->templateLoader = $templateLoader;
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

        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();

            $templateGroups = $this->templateLoader->getTemplateGroups($config->getRouteName(), $salesChannels);
            /** @var TemplateGroup[] $groups */
            foreach ($templateGroups as $languageId => $groups) {
                $language = $languages->get($languageId);

                $chain = $languageChains[$languageId];
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
                $context->setConsiderInheritance(true);
                $iterator = $this->iteratorFactory->createIterator($config->getDefinition());

                $this->eventDispatcher->dispatch(
                    new ProgressStartedEvent(
                        sprintf(
                            'Start indexing %s seo urls for language %s',
                            $config->getRouteName(),
                            $language->getName()
                        ),
                        $iterator->fetchCount()
                    ),
                    ProgressStartedEvent::NAME
                );

                while ($ids = $iterator->fetch()) {
                    $seoUrls = $this->seoUrlGenerator->generateSeoUrls($context, $seoUrlRoute, $ids, $groups);
                    $this->seoUrlPersister->updateSeoUrls($context, $config->getRouteName(), $ids, $seoUrls);

                    $this->eventDispatcher->dispatch(
                        new ProgressAdvancedEvent(\count($ids)),
                        ProgressAdvancedEvent::NAME
                    );
                }

                $this->eventDispatcher->dispatch(
                    new ProgressFinishedEvent(sprintf(
                        'Finished indexing %s seo urls for language %s',
                        $config->getRouteName(),
                        $language->getName()
                    )),
                    ProgressFinishedEvent::NAME
                );
            }
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

        $groupOffset = 0;
        $dataOffset = null;
        $routeOffset = 0;

        if ($lastId) {
            $groupOffset = $lastId['groupOffset'];
            $dataOffset = $lastId['dataOffset'];
            $routeOffset = $lastId['routeOffset'];
        }

        $routes = array_values($this->seoUrlRouteRegistry->getSeoUrlRoutes());

        if (!isset($routes[$routeOffset])) {
            return null;
        }

        $route = $routes[$routeOffset];

        $config = $route->getConfig();

        $templateGroups = $this->templateLoader->getTemplateGroups($config->getRouteName(), $salesChannels);

        $mapped = [];
        foreach ($templateGroups as $languageId => $groups) {
            $mapped[] = ['languageId' => $languageId, 'groups' => $groups];
        }

        if (!isset($mapped[$groupOffset])) {
            ++$routeOffset;

            return [
                'groupOffset' => 0,
                'dataOffset' => null,
                'routeOffset' => $routeOffset,
            ];
        }

        $group = $mapped[$groupOffset];

        $languageId = $group['languageId'];
        $groups = $group['groups'];

        $chain = $languageChains[$languageId];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
        $context->setConsiderInheritance(true);
        $iterator = $this->iteratorFactory->createIterator($config->getDefinition(), $dataOffset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            ++$groupOffset;

            return [
                'groupOffset' => $groupOffset,
                'dataOffset' => null,
                'routeOffset' => $routeOffset,
            ];
        }

        $seoUrls = $this->seoUrlGenerator->generateSeoUrls($context, $route, $ids, $groups);
        $this->seoUrlPersister->updateSeoUrls($context, $config->getRouteName(), $ids, $seoUrls);

        return [
            'groupOffset' => $groupOffset,
            'dataOffset' => $iterator->getOffset(),
            'routeOffset' => $routeOffset,
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $idsPerRoute = [];
        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            $extractResult = $seoUrlRoute->extractIdsToUpdate($event);

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

            $idsPerRoute[$seoUrlRoute->getConfig()->getRouteName()] = $extractResult;
        }
        $activeTemplateGroupsPerRoute = $this->loadAffectedTemplateGroups($event, array_keys($idsPerRoute));
        if (empty($activeTemplateGroupsPerRoute)) {
            return;
        }

        $context = Context::createDefaultContext();
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });
        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());

        /*
         * @var SeoUrlExtractIdResult
         */
        foreach ($idsPerRoute as $routeName => $extractResult) {
            $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($routeName);
            $config = $seoUrlRoute->getConfig();

            $templateLanguageGroups = $activeTemplateGroupsPerRoute[$routeName];

            /** @var TemplateGroup[] $groups */
            foreach ($templateLanguageGroups as $languageId => $groups) {
                $chain = $languageChains[$languageId];
                $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
                $context->setConsiderInheritance(true);
                foreach (array_chunk($extractResult->getIds(), 250) as $idsChunk) {
                    $seoUrls = $this->seoUrlGenerator->generateSeoUrls($context, $seoUrlRoute, $idsChunk, $groups);
                    $this->seoUrlPersister->updateSeoUrls($context, $config->getRouteName(), $idsChunk, $seoUrls);
                }
            }
        }
    }

    public static function getName(): string
    {
        return 'Swag.SeoUrlIndexer';
    }

    /**
     * Load Template groups affected by the given $event
     */
    private function loadAffectedTemplateGroups(EntityWrittenContainerEvent $event, array $routeNames): array
    {
        $activeTemplateGroupsPerRoute = [];
        $affectedSalesChannelIds = [[]];
        // load all templates per route and check if they are affected by the $event
        foreach ($routeNames as $routeName) {
            $seoUrlRoute = $this->seoUrlRouteRegistry->findByRouteName($routeName);
            $config = $seoUrlRoute->getConfig();
            $templateLanguageGroups = $this->templateLoader->getTemplateGroups($config->getRouteName(), []);
            $activeTemplateLanguageGroups = $this->filterAffectedTemplateGroups($templateLanguageGroups, $event, $seoUrlRoute);

            foreach ($activeTemplateLanguageGroups as $templateGroups) {
                /* @var TemplateGroup $templateGroup */
                foreach ($templateGroups as $templateGroup) {
                    $affectedSalesChannelIds[] = $templateGroup->getSalesChannelIds();
                }
            }

            $activeTemplateGroupsPerRoute[$routeName] = $activeTemplateLanguageGroups;
        }

        if (empty($activeTemplateGroupsPerRoute)) {
            return [];
        }

        // gather all sales channel ids from the affected templates and the load the sales channel entities
        $affectedSalesChannelIds = array_merge(...$affectedSalesChannelIds);
        $salesChannels = $this->fetchSalesChannels($affectedSalesChannelIds);

        // Assign the sales channel entities each TemplateGroup.
        foreach ($activeTemplateGroupsPerRoute as $templateLanguageGroups) {
            foreach ($templateLanguageGroups as $templateGroups) {
                /** @var TemplateGroup $templateGroup */
                foreach ($templateGroups as $templateGroup) {
                    $activeSalesChannelIdForTemplate = $templateGroup->getSalesChannelIds();

                    $activeSalesChannels = array_filter($salesChannels, function (?SalesChannelEntity $salesChannel) use ($activeSalesChannelIdForTemplate) {
                        if ($salesChannel === null) {
                            return in_array(null, $activeSalesChannelIdForTemplate, true);
                        }

                        return in_array($salesChannel->getId(), $activeSalesChannelIdForTemplate, true);
                    });

                    $templateGroup->setSalesChannels($activeSalesChannels);
                }
            }
        }

        return $activeTemplateGroupsPerRoute;
    }

    /**
     * Filters TemplateGroups which are affected by the given $event. TemplateGroups are only included in the result,
     * if they contain variables which could change with the given $event.
     */
    private function filterAffectedTemplateGroups(array $templateGroups, EntityWrittenContainerEvent $event, SeoUrlRouteInterface $route): array
    {
        $activeTemplateGroups = [];
        $definition = $route->getConfig()->getDefinition();
        $specialVariables = $route->getSeoVariables();
        foreach ($templateGroups as $languageId => $groups) {
            $activeGroups = array_filter($groups, function (TemplateGroup $group) use ($event,$specialVariables, $definition) {
                return $this->seoUrlGenerator->checkUpdateAffectsTemplate($event, $definition, $specialVariables, $group->getTemplate());
            });

            if (empty($activeGroups)) {
                continue;
            }

            $activeTemplateGroups[$languageId] = $activeGroups;
        }

        return $activeTemplateGroups;
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

    private function fetchSalesChannels(array $ids = []): array
    {
        $ids = array_filter($ids, function ($id) {
            return $id !== null;
        });

        $context = Context::createDefaultContext();
        $entities = $context->disableCache(function (Context $context) use ($ids) {
            return $this->salesChannelRepository->search((new Criteria($ids))->addAssociation('navigationCategory'), $context)->getEntities();
        });

        $salesChannels = $entities->getElements();
        // We add the "null" SalesChannel manually as it signals the fallback value if no other seo urls or
        // url templates are assigned to a entity/sales channel combination
        $salesChannels[] = null;

        return $salesChannels;
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
}
