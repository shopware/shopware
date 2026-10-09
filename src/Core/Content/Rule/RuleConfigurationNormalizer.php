<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\ContainerInterface;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 *
 * @phpstan-type ConditionRow array{id: string, parent_id: string|null, type: string, value: string|null, script_id: string|null}
 */
#[Package('fundamentals@after-sales')]
class RuleConfigurationNormalizer
{
    public function __construct(private readonly RuleConditionRegistry $ruleConditionRegistry)
    {
    }

    /**
     * @param list<ConditionRow> $conditions
     */
    public function checksum(array $conditions): ?string
    {
        foreach ($conditions as $condition) {
            if (!$this->isContainer($condition['type'])) {
                return Hasher::hash($this->normalize($conditions));
            }
        }

        return null;
    }

    /**
     * @param list<ConditionRow> $conditions
     *
     * @return list<mixed>
     */
    public function normalize(array $conditions): array
    {
        $childrenByParent = [];
        foreach ($conditions as $condition) {
            $childrenByParent[$condition['parent_id'] ?? ''][] = $condition;
        }

        $tree = $this->canonicalize($this->buildTree($childrenByParent, ''));
        \assert(\is_array($tree) && array_is_list($tree));

        return $tree;
    }

    /**
     * @param array<string, list<ConditionRow>> $childrenByParent
     *
     * @return list<array{type: string, scriptId: string|null, value: mixed, children: list<mixed>}>
     */
    private function buildTree(array $childrenByParent, string $parentId): array
    {
        $nodes = [];
        foreach ($childrenByParent[$parentId] ?? [] as $condition) {
            $nodes[] = [
                'type' => $condition['type'],
                'scriptId' => $condition['script_id'],
                'value' => json_decode($condition['value'] ?? '{}', true) ?? [],
                'children' => $this->buildTree($childrenByParent, $condition['id']),
            ];
        }

        return $nodes;
    }

    private function isContainer(string $type): bool
    {
        return $this->ruleConditionRegistry->has($type)
            && is_a($this->ruleConditionRegistry->getRuleClass($type), ContainerInterface::class, true);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $value = array_map($this->canonicalize(...), $value);

        if (!array_is_list($value)) {
            ksort($value, \SORT_STRING);

            return $value;
        }

        $sortKeys = array_map(static fn (mixed $item): string => Json::encode($item), $value);
        asort($sortKeys, \SORT_STRING);

        return array_map(static fn (int $index): mixed => $value[$index], array_keys($sortKeys));
    }
}
