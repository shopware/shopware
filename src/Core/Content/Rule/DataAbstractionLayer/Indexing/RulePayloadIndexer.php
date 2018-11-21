<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionStruct;
use Shopware\Core\Content\Rule\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
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

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        RepositoryInterface $ruleRepository,
        Serializer $serializer
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->ruleRepository = $ruleRepository;
        $this->serializer = $serializer;
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

    private function createIterator(Context $context): RepositoryIterator
    {
        return new RepositoryIterator($this->ruleRepository, $context);
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getRuleIds($event);
        $this->update($ids);
    }

    private function update(array $ruleIds): void
    {
        if (empty($ruleIds)) {
            return;
        }

        $ruleIds = array_values(array_map(function($id) { return Uuid::fromHexToBytes($id); }, $ruleIds));

        $conditions = $this->fetchConditions($ruleIds);
        $rules = $this->groupConditions($conditions);
        $query = $this->createUpdateQuery();

        foreach ($rules as $id => $rule) {
            $mainCondition = null;

            /** @var RuleConditionStruct $mainCondition */
            $mainCondition = $this->buildConditions($rule);

            $this->checkMainConditionIsSet($mainCondition);

            $mainRule = $this->convertToRule($mainCondition);
            $serialized = $this->serializer->serialize($mainRule, 'json');

            $query->setParameter('id', $id)
                ->setParameter('serialize', $serialized);
            $query->execute();
        }
    }

    private function convertToRule(RuleConditionStruct $condition): Rule
    {
        $ruleType = $condition->getType();
        /** @var Rule $rule */
        $rule = new $ruleType();

        if ($rule instanceof Container) {
            foreach ($condition->getChildren() ?? [] as $child) {
                $rule->addRule($this->convertToRule($child));
            }
        }

        if ($condition->getValue()) {
            $rule->assign($condition->getValue());
        }

        return $rule;
    }

    private function fetchConditions(array $ruleIds): array
    {
        $sql = <<<SQL
SELECT rc.*
FROM rule_condition rc
WHERE rc.rule_id IN (:ids)
ORDER BY rc.rule_id
SQL;

        return $this->connection->fetchAll($sql, ['ids' => $ruleIds], ['ids' => Connection::PARAM_STR_ARRAY]);
    }

    private function groupConditions(array $conditions): array
    {
        $rules = [];

        foreach ($conditions as $condition) {
            $rc = new RuleConditionStruct();
            $rc->assign($condition);

            if (isset($condition['value']) && !empty($condition['value'])) {
                $rc->setValue(json_decode($condition['value'], true));
            }
            $rules[$condition['rule_id']][] = $rc;
        }

        return $rules;
    }

    private function createUpdateQuery(): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->update('rule')
            ->set('payload', ':serialize')
            ->where('id = :id');

        return $query;
    }

    private function isParent(RuleConditionStruct $parentCondition, RuleConditionStruct $condition): bool
    {
        return $parentCondition->get('id') === $condition->get('parent_id');
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidLengthException
     */
    private function checkMainConditionIsNotSet(?RuleConditionStruct $mainCondition): void
    {
        if ($mainCondition) {
            throw new \RuntimeException('Multiple main conditions found for rule');
        }
    }

    /**
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidException
     * @throws \Shopware\Core\Framework\Exception\InvalidUuidLengthException
     */
    private function checkMainConditionIsSet(?RuleConditionStruct $mainCondition): void
    {
        if (!$mainCondition) {
            throw new \RuntimeException('Main condition not found for rule');
        }
    }

    private function getParentCondition(array $rule, RuleConditionStruct $condition): ?RuleConditionStruct
    {
        /** @var RuleConditionStruct $pCondition */
        foreach ($rule as $pCondition) {
            if ($this->isParent($pCondition, $condition)) {
                return $pCondition;
            }
        }

        return null;
    }

    private function buildConditions(array $conditions)
    {
        $mainCondition = null;
        foreach ($conditions as $condition) {
            /** @var RuleConditionStruct|null $parentCondition */
            $parentCondition = $this->getParentCondition($conditions, $condition);

            if (!isset($parentCondition)) {
                $this->checkMainConditionIsNotSet($mainCondition);

                $mainCondition = $condition;
                continue;
            }

            if ($parentCondition->getChildren() === null) {
                $parentCondition->setChildren(new RuleConditionCollection());
            }

            $parentCondition->getChildren()->add($condition);
        }

        return $mainCondition;
    }
}