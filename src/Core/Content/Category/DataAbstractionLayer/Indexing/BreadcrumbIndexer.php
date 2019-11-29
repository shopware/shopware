<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class BreadcrumbIndexer implements IndexerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var CacheClearer
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(
        EntityRepositoryInterface $languageRepository,
        EntityRepositoryInterface $categoryRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        CacheClearer $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        $this->languageRepository = $languageRepository;
        $this->categoryRepository = $categoryRepository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->iteratorFactory = $iteratorFactory;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $context = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
                Defaults::LIVE_VERSION
            );

            $iterator = $this->iteratorFactory->createIterator($this->categoryRepository->getDefinition());

            $this->eventDispatcher->dispatch(
                new ProgressStartedEvent(
                    sprintf('Start indexing category breadcrumb for language %s', $language->getName()),
                    $iterator->fetchCount()
                ),
                ProgressStartedEvent::NAME
            );

            while ($ids = $iterator->fetch()) {
                $this->update($ids, $context);

                $this->eventDispatcher->dispatch(
                    new ProgressAdvancedEvent(\count($ids)),
                    ProgressAdvancedEvent::NAME
                );
            }

            $this->eventDispatcher->dispatch(
                new ProgressFinishedEvent(sprintf('Finished indexing category breadcrumb for language %s', $language->getName())),
                ProgressFinishedEvent::NAME
            );
        }
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $languages = $this->languageRepository->search(new Criteria(), Context::createDefaultContext());
        $languages = array_values($languages->getElements());

        $languageOffset = 0;
        $dataOffset = null;
        if ($lastId) {
            $dataOffset = $lastId['dataOffset'];
            $languageOffset = $lastId['languageOffset'];
        }

        if (!isset($languages[$languageOffset])) {
            return null;
        }

        /** @var LanguageEntity $language */
        $language = $languages[$languageOffset];

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION
        );

        $iterator = $this->iteratorFactory->createIterator($this->categoryRepository->getDefinition(), $dataOffset);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            ++$languageOffset;

            return [
                'dataOffset' => $iterator->getOffset(),
                'languageOffset' => $languageOffset,
            ];
        }

        $this->update($ids, $context);

        return [
            'dataOffset' => $iterator->getOffset(),
            'languageOffset' => $languageOffset,
        ];
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $categories = $event->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        if (!$categories || $categories instanceof EntityDeletedEvent) {
            return;
        }

        $categoryIds = $categories->getIds();

        $languageIds = [$event->getContext()->getLanguageId()];

        $translations = $event->getEventByEntityName(CategoryTranslationDefinition::ENTITY_NAME);

        if ($translations) {
            $languageIds = array_merge($languageIds, array_column($translations->getIds(), 'languageId'));
        }

        $languages = $this->languageRepository->search(new Criteria($languageIds), $event->getContext());

        $categoryIds = array_merge(
            $categoryIds,
            $this->fetchChildren($categoryIds, $event->getContext()->getVersionId())
        );

        $categoryIds = array_filter(array_keys(array_flip($categoryIds)));

        /** @var LanguageEntity $language */
        foreach ($languages as $language) {
            $context = new Context(
                new SystemSource(),
                [],
                Defaults::CURRENCY,
                [$language->getId(), $language->getParentId(), Defaults::LANGUAGE_SYSTEM],
                Defaults::LIVE_VERSION
            );

            $this->update($categoryIds, $context);
        }
    }

    public function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $languageId = Uuid::fromHexToBytes($context->getLanguageId());

        $query = $this->connection->createQueryBuilder();
        $query->select('category.path');
        $query->from('category');
        $query->where('category.id IN (:ids)');
        $query->andWhere('category.version_id = :version');
        $query->setParameter('version', $versionId);
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);

        $paths = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $all = $ids;
        foreach ($paths as $path) {
            $path = explode('|', (string) $path);
            foreach ($path as $id) {
                $all[] = $id;
            }
        }

        $all = array_filter(array_values(array_keys(array_flip($all))));

        /** @var CategoryCollection $categories */
        $categories = $context->disableCache(
            function (Context $context) use ($all) {
                return $this->categoryRepository
                    ->search(new Criteria($all), $context)
                    ->getEntities();
            }
        );

        $tags = [];
        foreach ($ids as $id) {
            $path = $this->buildBreadcrumb($id, $categories);

            $this->connection->executeUpdate(
                '
                    INSERT INTO `category_translation`
                        (`category_id`, `category_version_id`, `language_id`, `breadcrumb`, `created_at`)
                    VALUES
                        (:categoryId, :versionId, :languageId, :breadcrumb, DATE(NOW()))
                    ON DUPLICATE KEY UPDATE `breadcrumb` = :breadcrumb',
                [
                    'categoryId' => Uuid::fromHexToBytes($id),
                    'versionId' => $versionId,
                    'languageId' => $languageId,
                    'breadcrumb' => json_encode($path),
                ]
            );

            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->categoryRepository->getDefinition());
        }

        $this->cache->invalidateTags($tags);
    }

    public static function getName(): string
    {
        return 'Swag.BreadcrumbIndexer';
    }

    private function buildBreadcrumb(string $id, CategoryCollection $categories): array
    {
        $category = $categories->get($id);

        if (!$category) {
            throw new CategoryNotFoundException($id);
        }

        $breadcrumb = [];
        if ($category->getParentId()) {
            $breadcrumb = $this->buildBreadcrumb($category->getParentId(), $categories);
        }

        $breadcrumb[$category->getId()] = $category->getTranslation('name');

        return $breadcrumb;
    }

    private function fetchChildren(array $categoryIds, string $versionId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('DISTINCT LOWER(HEX(category.id))');
        $query->from('category');

        $wheres = [];
        foreach ($categoryIds as $id) {
            $key = 'path' . $id;
            $wheres[] = 'category.path LIKE :' . $key;
            $query->setParameter($key, '%|' . $id . '|%');
        }

        $query->andWhere('(' . implode(' OR ', $wheres) . ')');
        $query->andWhere('category.version_id = :version');
        $query->setParameter('version', Uuid::fromHexToBytes($versionId));

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }
}
