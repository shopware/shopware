<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Swaps $elementId's component to $newType, keeping the same id. Carryover (policy 1 — never silently rewire):
 * primitive properties whose key and type match a $newType property are kept; wiring (data requirements and
 * context definitions) whose key matches a $newType reference property is kept; children of slots that exist
 * in $newType are kept. Children of slots absent from $newType are detached into {@see orphaned()}, wiring
 * keys with no matching $newType reference property are reported via {@see droppedWiring()}, and static
 * property values the new type cannot hold are reported via {@see droppedProperties()}.
 *
 * @internal
 */
#[Package('framework')]
final class ReplaceElement extends AbstractLayoutMutation
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
        private readonly string $elementId,
        private readonly string $newType,
    ) {
    }

    public function apply(array $tree): array
    {
        $this->requireRegistered($this->registry, $this->newType);

        // carryProperties accumulates into $droppedProperties, unlike the other channels which are reassigned;
        // reset it here so re-application reports only the current run's drops and the single-use contract is not
        // load-bearing for correctness.
        $this->droppedProperties = [];

        $node = $this->findNode($tree, $this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $properties = $this->registry->get($this->newType)->properties();
        $contextDefinitions = $node->getContextDefinitions();

        $keptDataRequirements = $this->carryWiring($node->getDataRequirements(), $properties);
        $keptProviders = $this->carryWiring($contextDefinitions->getAllProviders(), $properties);
        $keptConsumers = $this->carryWiring($contextDefinitions->getAllConsumers(), $properties);

        $this->droppedWiring = $this->droppedWiringKeys(
            [...array_keys($node->getDataRequirements()), ...array_keys($contextDefinitions->getAllProviders()), ...array_keys($contextDefinitions->getAllConsumers())],
            [...array_keys($keptDataRequirements), ...array_keys($keptProviders), ...array_keys($keptConsumers)],
        );

        $replacement = new ContentElement(
            $node->getId(),
            $this->newType,
            $keptDataRequirements,
            $this->carryProperties($node->getProperties(), $properties),
            $this->carrySlots($node),
            new ContextDefinitions($keptProviders, $keptConsumers),
        );

        // Whole subtree, not just the replaced element: a kept descendant may re-resolve if the new type drops a provider it consumed.
        $this->affected = $this->subtreeIds($replacement);

        return $this->replaceNode($tree, $this->elementId, $replacement);
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, PropertySpecification> $newTypeProperties
     *
     * @return array<string, mixed>
     */
    private function carryProperties(array $properties, array $newTypeProperties): array
    {
        $kept = [];

        foreach ($properties as $key => $value) {
            if (!isset($newTypeProperties[$key])) {
                $this->droppedProperties[$key] = $value;

                continue;
            }

            $type = $newTypeProperties[$key]->type();

            if (!$type->isPrimitive() || !$this->primitiveMatches($value, $type->type())) {
                $this->droppedProperties[$key] = $value;

                continue;
            }

            $kept[$key] = $value;
        }

        return $kept;
    }

    /**
     * @template TWiring
     *
     * @param array<string, TWiring> $wiring
     * @param array<string, PropertySpecification> $newTypeProperties
     *
     * @return array<string, TWiring>
     */
    private function carryWiring(array $wiring, array $newTypeProperties): array
    {
        $kept = [];

        foreach ($wiring as $key => $value) {
            if (isset($newTypeProperties[$key]) && !$newTypeProperties[$key]->type()->isPrimitive()) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    /**
     * @param list<string> $oldKeys
     * @param list<string> $keptKeys
     *
     * @return list<string>
     */
    private function droppedWiringKeys(array $oldKeys, array $keptKeys): array
    {
        return array_values(array_unique(array_diff($oldKeys, $keptKeys)));
    }

    /**
     * @return array<string, SlotContent>
     */
    private function carrySlots(ContentElement $node): array
    {
        $slotNames = array_map(static fn (SlotSpecification $slot): string => $slot->name(), $this->registry->get($this->newType)->slots());

        $kept = [];
        $orphaned = [];

        foreach ($node->getSlots() as $slotName => $slotContent) {
            if (\in_array($slotName, $slotNames, true)) {
                $kept[$slotName] = $slotContent;

                continue;
            }

            foreach (array_values($slotContent->getElements()) as $child) {
                $orphaned[] = $child;
            }
        }

        $this->orphaned = $orphaned;

        return $kept;
    }

    private function primitiveMatches(mixed $value, string $declaredType): bool
    {
        return match ($declaredType) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            default => false,
        };
    }
}
