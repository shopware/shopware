<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\NestedRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class NestedRuleValidator implements EventSubscriberInterface
{
    private Connection $connection;

    /**
     * @var string[]
     */
    private array $visitedRuleIds = [];

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostWriteValidationEvent::class => 'postValidate',
        ];
    }

    /**
     * @throws UnsupportedCommandTypeException
     */
    public function postValidate(PostWriteValidationEvent $event): void
    {
        $writeException = $event->getExceptions();
        $commands = $event->getCommands();
        $updateQueue = [];

        foreach ($commands as $command) {
            if ($command->getDefinition()->getClass() !== RuleConditionDefinition::class) {
                continue;
            }

            if ($command instanceof DeleteCommand) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $updateQueue[] = $command;

                continue;
            }

            if ($command instanceof UpdateCommand) {
                $updateQueue[] = $command;

                continue;
            }

            throw new UnsupportedCommandTypeException($command);
        }

        if (!empty($updateQueue)) {
            $this->validateCommands($updateQueue, $writeException);
        }
    }

    private function validateCondition(
        string $ruleId,
        WriteCommand $command,
        WriteException $writeException
    ): void {
        $violationList = new ConstraintViolationList();

        if ($this->hasCycle($ruleId)) {
            $violation = $this->buildViolation(
                'This condition is creating a nested loop cycle.',
                [],
                '/value/ruleId',
                'CONTENT__INVALID_RULE_NESTED_CYCLE'
            );
            $violationList->add($violation);
            $writeException->add(new WriteConstraintViolationException($violationList, $command->getPath()));
        }
    }

    /**
     * @param WriteCommand[] $commandQueue
     */
    private function validateCommands(
        array $commandQueue,
        WriteException $writeException
    ): void {
        $ruleIds = $this->getNestedRuleIds($commandQueue);

        foreach ($commandQueue as $command) {
            $conditionId = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);

            if (\array_key_exists($conditionId, $ruleIds)) {
                $ruleId = $ruleIds[$conditionId];
                $this->validateCondition($ruleId, $command, $writeException);
            }
        }
    }

    /**
     * @return string[]
     */
    private function getNestedRuleIds(array $commandQueue): array
    {
        $ids = array_map(function ($command) {
            $uuidBytes = $command->getPrimaryKey()['id'];

            return Uuid::fromBytesToHex($uuidBytes);
        }, $commandQueue);

        $query = 'SELECT LOWER(HEX(id)) AS id, JSON_UNQUOTE(JSON_EXTRACT(`value`, \'$.ruleId\')) as ruleId FROM rule_condition WHERE id IN (:ids) AND `type` = :type';

        $ruleIds = $this->connection->fetchAllAssociative($query, [
            'type' => NestedRule::NAME,
            'ids' => Uuid::fromHexToBytesList($ids),
        ], [
            'ids' => Connection::PARAM_STR_ARRAY,
        ]);

        return array_combine(array_column($ruleIds, 'id'), array_column($ruleIds, 'ruleId')) ?: [];
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        ?string $propertyPath = null,
        ?string $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), $parameters, $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            null,
            null,
            $code
        );
    }

    private function hasCycle(string $ruleId): bool
    {
        $this->visitedRuleIds = [];

        return $this->hasCycleInNestedRulesOfRule($ruleId);
    }

    private function hasCycleInNestedRulesOfRule(string $ruleId): bool
    {
        $query = 'SELECT LOWER(HEX(rule_id)) FROM rule_condition WHERE `type` = :type AND JSON_UNQUOTE(JSON_EXTRACT(`value`, \'$.ruleId\')) = :ruleId';
        $ruleIds = $this->connection->fetchFirstColumn($query, [
            'type' => NestedRule::NAME,
            'ruleId' => $ruleId,
        ]);

        foreach ($ruleIds as $nestedRuleId) {
            if ($ruleId === $nestedRuleId) {
                return true; // Self-referencing cycle
            }

            $isNewRuleId = $this->addAndCheckRuleId($nestedRuleId);

            if (!$isNewRuleId) {
                return true;
            }

            if ($this->hasCycleInNestedRulesOfRule($nestedRuleId)) {
                return true;
            }
        }

        return false;
    }

    private function addAndCheckRuleId(string $ruleId): bool
    {
        if (\array_key_exists($ruleId, $this->visitedRuleIds)) {
            return false;
        }

        $this->visitedRuleIds[$ruleId] = $ruleId;

        return true;
    }
}
