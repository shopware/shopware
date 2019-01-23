<?php declare(strict_types=1);

namespace Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\ConditionTree\ConditionRegistry;
use Shopware\Core\Framework\ConditionTree\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class PayloadIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var EventIdExtractorInterface
     */
    protected $eventIdExtractor;

    /**
     * @var EntityRepositoryInterface
     */
    protected $repository;

    /**
     * @var ConditionRegistry
     */
    protected $conditionRegistry;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractorInterface $eventIdExtractor,
        RepositoryInterface $repository,
        ConditionRegistry $conditionRegistry
        EventIdExtractor $eventIdExtractor,
        EntityRepositoryInterface $ruleRepository,
        Serializer $serializer,
        ConditionRegistry $conditionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache,
        string $entityName
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->repository = $repository;
        $this->repository = $ruleRepository;
        $this->serializer = $serializer;
        $this->conditionRegistry = $conditionRegistry;
    }

    public function index(\DateTime $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator($context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing ' . $this->getEntityDescription(), $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->update($ids);
            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing ' . $this->getEntityDescription())
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getEntityIds($event);
        $this->update($ids);
    }

    abstract protected function getEntityDescription(): string;

    protected function createIterator(Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->repository, $context);
    }

    abstract protected function update(array $ids): void;

    protected function buildNested(array $entities, ?string $parentId): array
    {
        $nested = [];
        foreach ($entities as $entity) {
            if ($entity['parent_id'] !== $parentId) {
                continue;
            }

            if (!$this->conditionRegistry->has($entity['type'])) {
                throw new ConditionTypeNotFound($entity['type']);
            }

            $conditionClass = $this->conditionRegistry->getConditionClass($entity['type']);
            $object = new $conditionClass();

            if ($entity['value'] !== null) {
                /* @var Entity $object */
                $object->assign(json_decode($entity['value'], true));
            }

            if ($object instanceof ContainerInterface) {
                $children = $this->buildNested($entities, $entity['id']);
                foreach ($children as $child) {
                    $object->addChild($child);
                }
            }

            $nested[] = $object;
        }

        return $nested;
    }
}
