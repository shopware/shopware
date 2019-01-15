<?php declare(strict_types=1);

namespace Shopware\Core\Content\ConditionTree\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\Util\EventIdExtractor;
use Shopware\Core\Framework\ConditionTree\ConditionRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Serializer;

/** TODO ProductStreams: refactor to be used by product streams and rules */
class PayloadIndexer implements IndexerInterface, EventSubscriberInterface
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
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var ConditionRegistry
     */
    private $conditionRegistry;

    /**
     * @var TagAwareAdapter
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var string
     */
    private $entityName;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        EntityRepositoryInterface $ruleRepository,
        Serializer $serializer,
        ConditionRegistry $conditionRegistry,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        TagAwareAdapter $cache,
        string $entityName
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->repository = $ruleRepository;
        $this->serializer = $serializer;
        $this->conditionRegistry = $conditionRegistry;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->entityName = $entityName;
    }

    public static function getSubscribedEvents()
    {
        return [
            '/** TODO **/' => 'refreshPlugin',
        ];
    }

    public function index(\DateTime $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator($context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing ' . $this->entityName, $iterator->getTotal())
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
            new ProgressFinishedEvent('Finished indexing ' . $this->entityName)
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getRuleIds($event);
        $this->update($ids);
    }

    public function refreshPlugin(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_OR, [
                    new NotFilter(
                        NotFilter::CONNECTION_AND,
                        [new EqualsAnyFilter($this->entityName . '.conditions.type', $this->conditionRegistry->getNames())]
                    ),
                    new EqualsFilter($this->entityName . '.invalid', true),
                ]
            )
        );

        $this->update($this->repository->searchIds($criteria, $context)->getIds());
    }

    private function createIterator(Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->repository, $context);
    }

    private function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        if ($this->cache->hasItem($this->entityName . 's_key')) {
            $this->cache->deleteItem('rules_key');
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $conditions = $this->connection->fetchAll(
            'SELECT rc.rule_id as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        $tags = [];
        foreach ($rules as $id => $rule) {
            $invalid = false;
            $serialized = null;
            try {
                $nested = $this->buildNested($rule, null);

                $tags[] = $this->cacheKeyGenerator->getEntityTag(Uuid::fromBytesToHex($id), RuleDefinition::class);

                //ensure the root rule is an AndRule
                $nested = new AndRule($nested);

                $serialized = $this->serializer->serialize($nested, 'json');
            } catch (ConditionTypeNotFound $exception) {
                $invalid = true;
            } finally {
                $this->connection->createQueryBuilder()
                    ->update('rule')
                    ->set('payload', ':serialize')
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

    private function buildNested(array $rules, ?string $parentId): array
    {
        $nested = [];
        foreach ($rules as $rule) {
            if ($rule['parent_id'] !== $parentId) {
                continue;
            }

            if (!$this->conditionRegistry->has($rule['type'])) {
                throw new ConditionTypeNotFound($rule['type']);
            }

            $ruleClass = $this->conditionRegistry->getConditionClass($rule['type']);
            $object = new $ruleClass();

            if ($rule['value'] !== null) {
                /* @var Rule $object */
                $object->assign(json_decode($rule['value'], true));
            }

            if ($object instanceof Container) {
                $children = $this->buildNested($rules, $rule['id']);
                foreach ($children as $child) {
                    $object->addRule($child);
                }
            }

            $nested[] = $object;
        }

        return $nested;
    }
}
