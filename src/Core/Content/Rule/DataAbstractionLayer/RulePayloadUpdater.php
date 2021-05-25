<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

class RulePayloadUpdater
{
    private Connection $connection;

    private RuleConditionRegistry $ruleConditionRegistry;

    public function __construct(Connection $connection, RuleConditionRegistry $ruleConditionRegistry)
    {
        $this->connection = $connection;
        $this->ruleConditionRegistry = $ruleConditionRegistry;
    }

    public function update(array $ids): array
    {
        $conditions = $this->connection->fetchAll(
            'SELECT LOWER(HEX(rc.rule_id)) as array_key, rc.* FROM rule_condition rc  WHERE rc.rule_id IN (:ids) ORDER BY rc.rule_id',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $rules = FetchModeHelper::group($conditions);

        $update = new RetryableQuery(
            $this->connection->prepare('UPDATE `rule` SET payload = :payload, invalid = :invalid WHERE id = :id')
        );

        $updated = [];
        foreach ($rules as $id => $rule) {
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
                $update->execute([
                    'id' => Uuid::fromHexToBytes($id),
                    'payload' => $serialized,
                    'invalid' => (int) $invalid,
                ]);
            }

            $updated[$id] = ['payload' => $serialized, 'invalid' => $invalid];
        }

        return $updated;
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
}
