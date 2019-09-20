<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Util\EventIdExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;

class ProductStreamIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var TagAwareAdapter
     */
    private $cache;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $productStreamRepository;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        EntityRepositoryInterface $productStreamRepository,
        Connection $connection,
        Serializer $serializer,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache,
        IteratorFactory $iteratorFactory,
        ProductDefinition $productDefinition
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->productStreamRepository = $productStreamRepository;
        $this->connection = $connection;
        $this->serializer = $serializer;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->cache = $cache;
        $this->iteratorFactory = $iteratorFactory;
        $this->productDefinition = $productDefinition;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $iterator = $this->iteratorFactory->createIterator($this->productStreamRepository->getDefinition());

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing product streams', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids);
            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing product streams'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $iterator = $this->iteratorFactory->createIterator(
            $this->productStreamRepository->getDefinition(),
            $lastId
        );

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductStreamIds($event);
        $this->update($ids);
    }

    private function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        if ($this->cache->hasItem('product_streams_key')) {
            $this->cache->deleteItem('product_streams_key');
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $filters = $this->connection->fetchAll(
            'SELECT product_stream_id as array_key, product_stream_filter.* FROM product_stream_filter  WHERE product_stream_id IN (:ids) ORDER BY product_stream_id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $filters = FetchModeHelper::group($filters);

        $tags = [];
        foreach ($filters as $id => $filter) {
            $invalid = false;
            $serialized = null;
            try {
                $nested = $this->buildNested($filter, null);

                $searchException = new SearchRequestException();
                $streamFilter = [];

                foreach ($nested as $value) {
                    $parsed = QueryStringParser::fromArray($this->productDefinition, $value, $searchException);
                    $streamFilter[] = QueryStringParser::toArray($parsed);
                }

                if ($searchException->getErrors()->current()) {
                    throw $searchException;
                }

                $tags[] = $this->cacheKeyGenerator->getEntityTag(Uuid::fromBytesToHex($id), $this->productStreamRepository->getDefinition());

                $serialized = $this->serializer->serialize($streamFilter, 'json');
            } catch (InvalidFilterQueryException | SearchRequestException $exception) {
                $invalid = true;
            } finally {
                $this->connection->createQueryBuilder()
                    ->update('product_stream')
                    ->set('api_filter', ':serialize')
                    ->set('invalid', ':invalid')
                    ->where('id = :id')
                    ->setParameter('id', $id)
                    ->setParameter('serialize', $serialized)
                    ->setParameter('invalid', (int) $invalid)
                    ->execute();
            }
        }

        $this->cache->invalidateTags($tags);
    }

    private function buildNested(array $entities, ?string $parentId): array
    {
        $nested = [];
        foreach ($entities as $entity) {
            if ($entity['parent_id'] !== $parentId) {
                continue;
            }

            if ($this->isJsonString($entity['parameters'])) {
                $entity['parameters'] = json_decode($entity['parameters'], true);
            }

            if ($this->isMultiFilter($entity['type'])) {
                $entity['queries'] = $this->buildNested($entities, $entity['id']);
            }

            $nested[] = $entity;
        }

        return $nested;
    }

    private function isJsonString($string): bool
    {
        if (!$string || !is_string($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function isMultiFilter(string $type): bool
    {
        return in_array($type, ['multi', 'not'], true);
    }
}
