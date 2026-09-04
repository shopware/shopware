<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation\Op;

use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\ResolvedByLoaderBranch;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * Swaps $elementId's component to $newType, keeping the same id. Carries over matching primitive
 * properties, wiring, and slot children; surfaces anything the new type cannot hold via
 * {@see orphaned()}, {@see droppedWiring()}, and {@see droppedProperties()}.
 *
 * The new type's default binding specification, when it has exactly one, is fill-applied after wiring carryover
 * (zero defaults is a no-op; more than one throws): fill-only semantics guarantee carried wiring is never
 * overwritten by the default. A stored property under one of the new type's `resolvedBy` storage keys is likewise
 * carryable, subject to a shape check against the storage key's loader branch (see {@see carryProperties()}).
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
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly BindingApplicator $bindingApplicator,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $this->requireRegistered($this->registry, $this->newType);

        // carryProperties accumulates into $droppedProperties, unlike the other channels which are reassigned;
        // reset it here so re-application reports only the current run's drops and the single-use contract is not
        // load-bearing for correctness.
        $this->droppedProperties = [];

        $node = $tree->find($this->elementId);

        if ($node === null) {
            throw ContentSystemException::mutationTargetNotFound($this->elementId);
        }

        $properties = $this->registry->get($this->newType)->properties();
        $contextDefinitions = $node->contextDefinitions;
        $default = $this->resolveDefaultSpecification($this->bindingRegistry, $this->newType);

        $keptDataRequirements = $this->carryWiring($node->dataRequirements, $properties);
        $keptProviders = $this->carryWiring($contextDefinitions->getAllProviders(), $properties);
        $keptConsumers = $this->carryWiring($contextDefinitions->getAllConsumers(), $properties);
        $keptAttributedSpecifications = array_intersect_key($node->attributedSpecifications, $keptDataRequirements);

        $this->droppedWiring = $this->droppedWiringKeys(
            [...array_keys($node->dataRequirements), ...array_keys($contextDefinitions->getAllProviders()), ...array_keys($contextDefinitions->getAllConsumers())],
            [...array_keys($keptDataRequirements), ...array_keys($keptProviders), ...array_keys($keptConsumers)],
        );

        // The id rides along untouched, and so does the style: it is universal and type-independent, so it carries
        // over unconditionally on a type swap. Every field the swap does change is named below.
        $replacement = $node
            ->withComponent($this->newType)
            ->withDataRequirements($keptDataRequirements)
            // Carried/authored values win; the new type's primitive defaults fill only the keys it does not carry
            // (absent, or dropped as type-incompatible) — mirroring scaffoldElement so a default is honored on
            // replace just as it is on insert.
            ->withProperties($this->carryProperties($node->properties(), $properties, $default) + $this->primitiveDefaults($this->registry, $this->newType))
            ->withSlots($this->carrySlots($node))
            ->withContextDefinitions(new ContextDefinitions($keptProviders, $keptConsumers))
            // attribution follows the carried data requirements, not the provider/consumer sets: an entry survives
            // only while the reference wiring it attributes still exists on the replacement
            ->withAttributedSpecifications($keptAttributedSpecifications);

        if ($default !== null) {
            $replacement = $this->bindingApplicator->applyFillOnly($replacement, $default, $default->qualifiedId());
        }

        // Whole subtree, not just the replaced element: a kept descendant may re-resolve if the new type drops a provider it consumed.
        $this->affected = $this->subtreeIds($replacement);

        return $tree->replace($this->elementId, $replacement);
    }

    /**
     * A stored property key survives the type swap two ways: it matches a new-type primitive property (the
     * pre-existing rule), or it is one of the new type's default specification's `resolvedBy` storage keys and the
     * stored value's shape matches that key's loader branch (a string for `entity`, a list of strings for
     * `entity_collection`) — deliberately stricter than the serve path's tolerant list filtering, so a partially
     * valid stored list is dropped-and-reported here rather than silently shrunk downstream. Neither rule reuses
     * {@see primitiveMatches()}: a storage key is undeclared by design, so it is never a new-type property, and
     * coupling "declared string property" to "entity storage key" by shape coincidence would coincidentally match
     * a declared string property with the same name as an unrelated storage key.
     *
     * @param array<string, StoredValue> $properties
     * @param array<string, PropertySpecification> $newTypeProperties
     *
     * @return array<string, StoredValue>
     */
    private function carryProperties(array $properties, array $newTypeProperties, ?BindingSpecification $default): array
    {
        $carryableStorageKeys = $this->carryableStorageKeys($default);
        $kept = [];

        foreach ($properties as $key => $value) {
            if (!isset($newTypeProperties[$key])) {
                $branch = $carryableStorageKeys[$key] ?? null;

                if ($branch !== null && $branch->matchesStoredValueShape($value)) {
                    $kept[$key] = $value;

                    continue;
                }

                $this->droppedProperties[$key] = $value;

                continue;
            }

            $type = $newTypeProperties[$key]->type();
            $declaredType = $type->type();

            if (!$type->isPrimitive() || !\is_string($declaredType) || !$this->primitiveMatches($value, $declaredType)) {
                $this->droppedProperties[$key] = $value;

                continue;
            }

            $kept[$key] = $value;
        }

        return $kept;
    }

    /**
     * The new type's `resolvedBy` storage keys, derived from its default specification's canonical `resolves`
     * entries: for each entry whose loader source is one of the two built-in resolvedBy loaders
     * ({@see ResolvedByLoaderBranch::fromLoaderSource()}), the entry's `config['property']` is the storage
     * key. An entry whose loader is neither built-in contributes no carryable key.
     *
     * @return array<string, ResolvedByLoaderBranch>
     */
    private function carryableStorageKeys(?BindingSpecification $default): array
    {
        if ($default === null) {
            return [];
        }

        $storageKeys = [];

        foreach ($default->resolves() as $binding) {
            $branch = ResolvedByLoaderBranch::fromLoaderSource($binding->loader);

            if ($branch === null) {
                continue;
            }

            $property = $binding->config['property'] ?? null;

            if (!\is_string($property)) {
                continue;
            }

            $storageKeys[$property] = $branch;
        }

        return $storageKeys;
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
     * @return array<string, list<StoredElement>>
     */
    private function carrySlots(StoredElement $node): array
    {
        $slotNames = array_map(static fn (SlotSpecification $slot): string => $slot->name(), $this->registry->get($this->newType)->slots());

        $kept = [];
        $orphaned = [];

        foreach ($node->slots as $slotName => $children) {
            if (\in_array($slotName, $slotNames, true)) {
                $kept[$slotName] = $children;

                continue;
            }

            foreach ($children as $child) {
                $orphaned[] = $child;
            }
        }

        $this->orphaned = $orphaned;

        return $kept;
    }

    /**
     * The declared primitive type judges the raw payload, so the stored value is unwrapped to compare against it.
     * A list or map variant unwraps to an array and matches no primitive type, which is the intended answer.
     */
    private function primitiveMatches(StoredValue $value, string $declaredType): bool
    {
        $raw = $value->jsonSerialize();

        return match ($declaredType) {
            'string' => \is_string($raw),
            'integer' => \is_int($raw),
            'number' => \is_int($raw) || \is_float($raw),
            'boolean' => \is_bool($raw),
            default => false,
        };
    }
}
