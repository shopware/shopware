<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use function Flag\next741;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
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

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        SeoUrlGenerator $seoUrlGenerator,
        SeoUrlPersister $seoUrlPersister,
        SeoUrlTemplateLoader $templateLoader,
        DefinitionInstanceRegistry $definitionRegistry,
        SeoUrlRouteRegistry $seoUrlRouteRegistry,
        EntityRepositoryInterface $languageRepository,
        IteratorFactory $iteratorFactory
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
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        // skip if feature is disabled
        if (!next741()) {
            return;
        }

        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());

        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();
            $repo = $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());

            $templateGroups = $this->templateLoader->getTemplateGroups($config->getRouteName());
            /** @var TemplateGroup[] $groups */
            foreach ($templateGroups as $languageId => $groups) {
                $language = $languages->get($languageId);

                $chain = $this->getLanguageIdChain($languageId);
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

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        // skip if feature is disabled
        if (!next741()) {
            return;
        }

        /** @var SeoUrlRouteInterface $seoUrlRoute */
        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $seoUrlRoute) {
            $config = $seoUrlRoute->getConfig();
            $ids = $this->getIdsByDefinition($event, $seoUrlRoute);
            if (empty($ids)) {
                continue;
            }

            $templateGroups = $this->templateLoader->getTemplateGroups($config->getRouteName());
            /** @var TemplateGroup[] $groups */
            foreach ($templateGroups as $languageId => $groups) {
                $chain = $this->getLanguageIdChain($languageId);
                $context = new Context(new Context\SystemSource(), [], Defaults::CURRENCY, $chain);
                foreach (array_chunk($ids, 250) as $idsChunk) {
                    $seoUrls = $this->seoUrlGenerator->generateSeoUrls($context, $seoUrlRoute, $idsChunk, $groups);
                    $this->seoUrlPersister->updateSeoUrls($context, $config->getRouteName(), $idsChunk, $seoUrls);
                }
            }
        }
    }

    private function getIdsByDefinition(EntityWrittenContainerEvent $generic, SeoUrlRouteInterface $route): array
    {
        $ids = [];

        $config = $route->getConfig();
        $definition = $config->getDefinition();

        $criteria = new Criteria();
        $route->prepareCriteria($criteria);
        $associations = array_keys($criteria->getAssociations());

        $event = $generic->getEventByDefinition($definition->getClass());
        if ($event) {
            $ids = $event->getIds();
        }

        $oneToManyAssociations = $definition->getFields()->filterInstance(OneToManyAssociationField::class);

        /** @var OneToManyAssociationField $oneToMany */
        foreach ($oneToManyAssociations as $oneToMany) {
            // only check for associations that are loaded with the entity
            if (!in_array($oneToMany->getPropertyName(), $associations, true)) {
                continue;
            }
            $localColumn = $oneToMany->getReferenceField();
            $referenceField = $oneToMany->getReferenceDefinition()->getFields()->getByStorageName($localColumn);
            $propertyName = $referenceField->getPropertyName();

            $event = $generic->getEventByDefinition($oneToMany->getReferenceDefinition()->getClass());
            if (!$event) {
                continue;
            }
            foreach ($event->getPayloads() as $payload) {
                if (isset($payload[$propertyName])) {
                    $ids[] = $payload[$propertyName];
                }
            }
        }

        $manyToManyAssociations = $definition->getFields()->filterInstance(ManyToManyAssociationField::class);
        /** @var ManyToManyAssociationField $manyToMany */
        foreach ($manyToManyAssociations as $manyToMany) {
            // only check for associations that are loaded with the entity
            if (!in_array($manyToMany->getPropertyName(), $associations, true)) {
                continue;
            }
            $mappingDefinition = $manyToMany->getMappingDefinition();
            $localColumn = $manyToMany->getMappingLocalColumn();
            $referenceField = $mappingDefinition->getFields()->getByStorageName($localColumn);
            $propertyName = $referenceField->getPropertyName();

            $event = $generic->getEventByDefinition($mappingDefinition->getClass());
            if (!$event) {
                continue;
            }
            foreach ($event->getPayloads() as $payload) {
                if (isset($payload[$propertyName])) {
                    $ids[] = $payload[$propertyName];
                }
            }
        }

        return array_unique($ids);
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
}
