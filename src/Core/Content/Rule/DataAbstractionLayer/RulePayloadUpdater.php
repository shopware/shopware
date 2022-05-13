<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\NestedRuleNotFound;
use Shopware\Core\Framework\App\Event\AppScriptConditionEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Rule\NestedRule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RulePayloadUpdater implements EventSubscriberInterface
{
    private Connection $connection;

    private RuleConditionRegistry $ruleConditionRegistry;

    /**
     * @var string[]
     */
    private array $ruleIds;

    /**
     * @var string[]
     */
    private array $nestedRuleIds;

    /**
     * @internal
     */
    public function __construct(Connection $connection, RuleConditionRegistry $ruleConditionRegistry)
    {
        $this->connection = $connection;
        $this->ruleConditionRegistry = $ruleConditionRegistry;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppScriptConditionEvents::APP_SCRIPT_CONDITION_WRITTEN_EVENT => 'updatePayloads',
            PreWriteValidationEvent::class => 'triggerChangeset',
            'rule_condition.written' => 'ruleConditionWritten',
        ];
    }

    public function update(array $ids): array
    {
        $this->ruleIds = [];
        $this->nestedRuleIds = [];
        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET payload = :payload, invalid = :invalid WHERE id = :id')
        );

        $rules = $this->buildNestedRules($ids);

        // Loop over all rules and buildNested until all depended rules are available and no unprocessed rules remain
        $unprocessedRules = $rules;
        $processedRuleIds = [];
        $updated = [];
        while (!empty($unprocessedRules)) {
            foreach ($rules as $id => &$rule) {
                if (\in_array($id, $processedRuleIds, true)) {
                    continue;
                }
                if (!\array_key_exists($id, $this->nestedRuleIds)) {
                    unset($unprocessedRules[$id]);
                    $processedRuleIds[$id] = $id;
                    $updated[$id] = $this->updateSerializedRule($rule, $id, $update);

                    continue;
                }
                if (!empty(array_diff($this->nestedRuleIds[$id], $processedRuleIds))) {
                    continue;
                }

                foreach ($rule as &$condition) {
                    if ($condition['type'] === NestedRule::NAME) {
                        if (!\array_key_exists('value', $condition) || !($value = json_decode($condition['value'], true)) || !\array_key_exists('ruleId', $value)) {
                            throw new NestedRuleNotFound();
                        }
                        $condition['nestedRule'] = $rules[$value['ruleId']];
                    }
                }

                unset($unprocessedRules[$id]);
                $processedRuleIds[$id] = $id;
                $updated[$id] = $this->updateSerializedRule($rule, $id, $update);
            }
            unset($rule);
        }

        return $updated;
    }

    public function updatePayloads(EntityWrittenEvent $event): void
    {
        $ruleIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT rc.rule_id
                FROM rule_condition rc
                INNER JOIN app_script_condition rs ON rc.script_id = rs.id
                WHERE rs.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_values($event->getIds()))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (empty($ruleIds)) {
            return;
        }

        $this->update(Uuid::fromBytesToHexList($ruleIds));
    }

    /**
     * @throws UnsupportedCommandTypeException
     */
    public function triggerChangeset(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();

        foreach ($commands as $command) {
            if ($command->getDefinition()->getClass() !== RuleConditionDefinition::class) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                $command->requestChangeSet();

                continue;
            }

            if ($command instanceof InsertCommand) {
                continue;
            }

            if ($command instanceof UpdateCommand) {
                if (
                    $command->hasField('value') && \array_key_exists('value', $command->getPayload())
                    && $command->getPayload()['value']
                    && ($value = json_decode($command->getPayload()['value'], true))
                    && \array_key_exists('ruleId', $value)
                ) {
                    $command->requestChangeSet();
                }

                continue;
            }

            throw new UnsupportedCommandTypeException($command);
        }
    }

    public function ruleConditionWritten(EntityWrittenEvent $event): void
    {
        $ids = [];

        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() === EntityWriteResult::OPERATION_INSERT) {
                continue;
            }

            $changeSet = $result->getChangeSet();
            if (!$changeSet) {
                continue;
            }

            $type = $changeSet->getAfter('type');

            if ($type !== NestedRule::NAME) {
                continue;
            }

            if (!$changeSet->hasChanged('value')) {
                continue;
            }

            $beforeValue = json_decode($changeSet->getBefore('value'), true);
            $ids[] = $beforeValue && \array_key_exists('ruleId', $beforeValue) ? $beforeValue['ruleId'] : null;

            if (!($afterValue = json_decode($changeSet->getAfter('value'), true)) || !\array_key_exists('ruleId', $afterValue)) {
                throw new NestedRuleNotFound();
            }
            $ids[] = $afterValue['ruleId'];
        }

        $ids = array_filter(array_unique($ids));

        if (empty($ids)) {
            return;
        }

        $this->update($ids);
    }

    private function updateSerializedRule(array $rule, string $id, RetryableQuery $query): array
    {
        $invalid = false;
        $serialized = null;

        try {
            $nested = $this->buildNested($rule, null);

            //ensure the root rule is an AndRule
            $nested = new AndRule($nested);

            $serialized = serialize($nested);
        } catch (ConditionTypeNotFound $exception) {
            $invalid = true;
        } finally {
            $query->execute([
                'id' => Uuid::fromHexToBytes($id),
                'payload' => $serialized,
                'invalid' => (int) $invalid,
            ]);
        }

        return ['payload' => $serialized, 'invalid' => $invalid];
    }

    private function buildNestedRules(array $ids): array
    {
        $ids = array_combine($ids, $ids);
        $rules = $this->getRulesToUpdate($ids);

        $nestedRuleIds = [];
        foreach ($rules as $id => &$rule) {
            foreach ($rule as $condition) {
                if ($condition['type'] === NestedRule::NAME) {
                    if (!\array_key_exists('value', $condition) || !($value = json_decode($condition['value'], true)) || !\array_key_exists('ruleId', $value)) {
                        throw new NestedRuleNotFound();
                    }

                    $nestedRuleIds[$value['ruleId']] = $value['ruleId'];
                    $this->nestedRuleIds[$id][$value['ruleId']] = $value['ruleId'];
                }
            }
        }

        $this->ruleIds = array_merge($this->ruleIds, $ids);

        $unprocessedRuleIds = array_diff($nestedRuleIds, $this->ruleIds);

        if (\count($unprocessedRuleIds) > 0) {
            $rules = array_merge($rules, $this->buildNestedRules($unprocessedRuleIds));
        }

        return $rules;
    }

    private function buildNested(array $rules, ?string $parentId): array
    {
        $nested = [];
        foreach ($rules as $rule) {
            if ($rule['parent_id'] !== $parentId) {
                continue;
            }

            if (!$this->ruleConditionRegistry->has($rule['type'])) {
                throw new ConditionTypeNotFound($rule['type']);
            }

            $ruleClass = $this->ruleConditionRegistry->getRuleClass($rule['type']);
            $object = new $ruleClass();

            if ($object instanceof ScriptRule) {
                $object->assign([
                    'script' => $rule['script'] ?? '',
                    'lastModified' => $rule['lastModified'] ? new \DateTimeImmutable($rule['lastModified']) : null,
                    'identifier' => $rule['identifier'] ?? null,
                    'values' => $rule['value'] ? json_decode($rule['value'], true) : [],
                ]);

                $nested[] = $object;

                continue;
            } elseif ($object instanceof NestedRule) {
                $object->assign([
                    'rule' => new AndRule($this->buildNested($rule['nestedRule'], null)),
                ]);
            }

            if ($rule['value'] !== null) {
                $object->assign(json_decode($rule['value'], true));
            }

            if ($object instanceof ContainerInterface) {
                $children = $this->buildNested($rules, $rule['id']);
                foreach ($children as $child) {
                    $object->addRule($child);
                }
            }

            $nested[] = $object;
        }

        return $nested;
    }

    private function getRulesToUpdate(array $ids): array
    {
        $conditions = $this->connection->fetchAll(
            'SELECT LOWER(HEX(rc.rule_id)) as array_key, rc.*, rs.script, rs.identifier, rs.updated_at as lastModified
            FROM rule_condition rc
            LEFT JOIN app_script_condition rs ON rc.script_id = rs.id AND rs.active = 1
            WHERE rc.rule_id IN (:ids)
            ORDER BY rc.rule_id',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        $nestedRuleIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(rc.rule_id))
            FROM rule_condition rc
            WHERE rc.type = :type AND JSON_UNQUOTE(JSON_EXTRACT(rc.value, \'$.ruleId\')) IN (:ids)',
            ['ids' => $ids, 'type' => NestedRule::NAME],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        if (!empty($nestedRuleIds)) {
            $rules = array_merge($rules, $this->getRulesToUpdate($nestedRuleIds));
        }

        return $rules;
    }
}
