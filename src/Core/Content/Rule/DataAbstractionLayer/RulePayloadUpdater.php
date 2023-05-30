<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\ConditionTypeNotFound;
use Shopware\Core\Framework\App\Event\AppScriptConditionEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('business-ops')]
class RulePayloadUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly RuleConditionRegistry $ruleConditionRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppScriptConditionEvents::APP_SCRIPT_CONDITION_WRITTEN_EVENT => 'updatePayloads',
        ];
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, array{payload: string|null, invalid: bool}>
     */
    public function update(array $ids): array
    {
        $conditions = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(rc.rule_id)) as array_key, rc.*, rs.script, rs.identifier, rs.updated_at as lastModified
            FROM rule_condition rc
            LEFT JOIN app_script_condition rs ON rc.script_id = rs.id AND rs.active = 1
            WHERE rc.rule_id IN (:ids)
            ORDER BY rc.rule_id, rc.position',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $rules = FetchModeHelper::group($conditions);

        $update = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `rule` SET payload = :payload, invalid = :invalid WHERE id = :id')
        );

        $updated = [];
        /** @var string $id */
        foreach ($rules as $id => $rule) {
            $invalid = false;
            $serialized = null;

            try {
                $nested = $this->buildNested($rule, null);

                //ensure the root rule is an AndRule
                $nested = new AndRule($nested);

                $serialized = serialize($nested);
            } catch (ConditionTypeNotFound) {
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

    public function updatePayloads(EntityWrittenEvent $event): void
    {
        $ruleIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT rc.rule_id
                FROM rule_condition rc
                INNER JOIN app_script_condition rs ON rc.script_id = rs.id
                WHERE rs.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_values($event->getIds()))],
            ['ids' => ArrayParameterType::STRING]
        );

        if (empty($ruleIds)) {
            return;
        }

        $this->update(Uuid::fromBytesToHexList($ruleIds));
    }

    /**
     * @param array<string, mixed> $rules
     *
     * @return list<Rule>
     */
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
                    'values' => $rule['value'] ? json_decode((string) $rule['value'], true, 512, \JSON_THROW_ON_ERROR) : [],
                ]);

                $nested[] = $object;

                continue;
            }

            if ($rule['value'] !== null) {
                $object->assign(json_decode((string) $rule['value'], true, 512, \JSON_THROW_ON_ERROR));
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
