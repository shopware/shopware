<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use function Flag\next741;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Language\LanguageEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlGenerator;
use Shopware\Storefront\Framework\Seo\SeoUrlPersister;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\SeoUrlTemplateLoader;
use Shopware\Storefront\Framework\Seo\SeoUrlTemplate\TemplateGroup;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        EntityRepositoryInterface $salesChannelRepository
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
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        // skip if feature is disabled
        if (!next741()) {
            return;
        }

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
                $context = new Context(new Context\SystemSource(), [], Defaults::CURRENCY, $chain);
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
        // skip if feature is disabled
        if (!next741()) {
            return null;
        }

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
        $context = new Context(new Context\SystemSource(), [], Defaults::CURRENCY, $chain);
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
        // skip if feature is disabled
        if (!next741()) {
            return;
        }

        $context = Context::createDefaultContext();
        $languages = $context->disableCache(function (Context $context) {
            return $this->languageRepository->search(new Criteria(), $context);
        });

        $languageChains = $this->fetchLanguageChains($languages->getEntities()->getElements());
        $salesChannels = $this->fetchSalesChannels();

        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();
            $ids = $seoUrlRoute->extractIdsToUpdate($event);
            if (empty($ids)) {
                continue;
            }

            $templateGroups = $this->templateLoader->getTemplateGroups($config->getRouteName(), $salesChannels);
            /** @var TemplateGroup[] $groups */
            foreach ($templateGroups as $languageId => $groups) {
                $chain = $languageChains[$languageId];
                $context = new Context(new Context\SystemSource(), [], Defaults::CURRENCY, $chain);
                foreach (array_chunk($ids, 250) as $idsChunk) {
                    $seoUrls = $this->seoUrlGenerator->generateSeoUrls($context, $seoUrlRoute, $idsChunk, $groups);
                    $this->seoUrlPersister->updateSeoUrls($context, $config->getRouteName(), $idsChunk, $seoUrls);
                }
            }
        }
    }

    private function getLanguageIdChain($languageId): array
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

    private function fetchSalesChannels(): array
    {
        $context = Context::createDefaultContext();
        $entities = $context->disableCache(function (Context $context) {
            return $this->salesChannelRepository->search(new Criteria(), $context)->getEntities();
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
