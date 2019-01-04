<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Content\Rule\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;

class RulePayloadIndexer implements IndexerInterface
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
     * @var RepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        RepositoryInterface $ruleRepository,
        Serializer $serializer,
        CacheItemPoolInterface $cache
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->ruleRepository = $ruleRepository;
        $this->serializer = $serializer;
        $this->cache = $cache;
    }

    public function index(\DateTime $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator($context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing rules', $iterator->getTotal())
        );

        /** @var EntitySearchResult $ids */
        while ($ids = $iterator->fetch()) {
            $this->update($ids->getIds());
            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing rules')
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getRuleIds($event);
        $this->update($ids);
    }

    private function createIterator(Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->ruleRepository, $context);
    }

    private function update(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        if ($this->cache->hasItem('rules_key')) {
            $this->cache->deleteItem('rules_key');
        }

        $bytes = array_values(array_map(function ($id) { return Uuid::fromHexToBytes($id); }, $ids));

        $conditions = $this->connection->fetchAll(
            'SELECT rc.rule_id as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
            ['ids' => $bytes],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        foreach ($rules as $id => $rule) {
            $nested = $this->buildNested($rule, null);

            //ensure the root rule is an AndRule
            if (\count($nested) !== 1 || !$nested[0] instanceof AndRule) {
                $nested = new AndRule($nested);
            } else {
                $nested = array_shift($nested);
            }

            $serialized = $this->serializer->serialize($nested, 'json');

            $this->connection->createQueryBuilder()
                ->update('rule')
                ->set('payload', ':serialize')
                ->where('id = :id')
                ->setParameter('id', $id)
                ->setParameter('serialize', $serialized)
                ->execute();
        }
    }

    private function buildNested(array $rules, ?string $parentId): array
    {
        $nested = [];
        foreach ($rules as $rule) {
            if ($rule['parent_id'] !== $parentId) {
                continue;
            }

            $object = new $rule['type']();
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
