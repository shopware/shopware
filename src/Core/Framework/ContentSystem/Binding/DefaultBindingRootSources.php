<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mutation\AbstractLayoutMutation;
use Shopware\Core\Framework\Log\Package;

/**
 * The root sources on which the auto-applied default binding specification of a type, or of every element of a
 * preset, resolves, so a picker can hide what an insert without a named binding specification would reject.
 * `null` means no root-source restriction; a list holds the ids of {@see RootSourceRegistry::knownRootSources()}
 * in that order, and an empty list means the insert fails on every root source.
 *
 * It restates three request-path rules: a scoped value resolves only for a root source that is a key of its scoped
 * map ({@see RootSourceConfigMap::collapse()}), the default fills only the keys an element does not wire yet
 * ({@see BindingApplicator::applyFillOnly()}), and the default is the type's default specification, of which more
 * than one fails ({@see AbstractLayoutMutation::resolveDefaultSpecification()}). More than one default answers `[]`,
 * because the insert then fails with `bindingSpecificationDefaultAmbiguous` on every root source.
 *
 * @internal
 */
#[Package('framework')]
final class DefaultBindingRootSources
{
    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingRegistry,
        private readonly RootSourceRegistry $rootSourceRegistry,
    ) {
    }

    /**
     * Exact for inserting the type without a named binding specification: the scaffolded element wires nothing, so
     * the default resolves every one of its entries.
     *
     * @return list<string>|null
     */
    public function forType(string $type): ?array
    {
        return $this->forDefaultOf($type, []);
    }

    /**
     * The intersection over every element and every slot child at any depth, in the decoded form a preset insert
     * attaches. Keys an element already wires never restrict, because the fill-only default skips them.
     *
     * @param list<StoredElement> $elements
     *
     * @return list<string>|null
     */
    public function forElements(array $elements): ?array
    {
        $restrictions = array_merge([], ...array_map($this->restrictionsOf(...), $elements));

        if ($restrictions === []) {
            return null;
        }

        return $this->knownIn($restrictions);
    }

    /**
     * @return list<array<array-key, mixed>> one root-source keyed set per restricting element of the subtree
     */
    private function restrictionsOf(StoredElement $element): array
    {
        $collected = [];
        $own = $this->forDefaultOf($element->component, $element->dataRequirements);

        if ($own !== null) {
            $collected[] = [array_flip($own)];
        }

        foreach ($element->slots as $children) {
            foreach ($children as $child) {
                $collected[] = $this->restrictionsOf($child);
            }
        }

        return array_merge([], ...$collected);
    }

    /**
     * @param array<string, mixed> $wiredKeys keys the element already wires, which the fill-only default skips
     *
     * @return list<string>|null
     */
    private function forDefaultOf(string $type, array $wiredKeys): ?array
    {
        $defaults = $this->defaultsOf($type);

        if ($defaults === []) {
            return null;
        }

        if (\count($defaults) > 1) {
            return [];
        }

        return $this->forEntries(array_diff_key($defaults[0]->resolves(), $wiredKeys));
    }

    /**
     * @return list<BindingSpecification>
     */
    private function defaultsOf(string $type): array
    {
        return array_values(array_filter(
            $this->bindingRegistry->byType($type),
            static fn (BindingSpecification $specification): bool => $specification->isDefault(),
        ));
    }

    /**
     * @param array<string, LoaderBinding> $entries
     *
     * @return list<string>|null
     */
    private function forEntries(array $entries): ?array
    {
        $scopedMaps = [];

        foreach ($entries as $binding) {
            foreach ($binding->config as $value) {
                $map = RootSourceConfigMap::scopeMap($value);

                if ($map !== null) {
                    $scopedMaps[] = $map;
                }
            }
        }

        if ($scopedMaps === []) {
            return null;
        }

        return $this->knownIn($scopedMaps);
    }

    /**
     * @param non-empty-list<array<array-key, mixed>> $keyedSets
     *
     * @return list<string>
     */
    private function knownIn(array $keyedSets): array
    {
        return array_values(array_filter(
            $this->rootSourceRegistry->knownRootSources(),
            static function (string $rootSource) use ($keyedSets): bool {
                foreach ($keyedSets as $keyedSet) {
                    if (!\array_key_exists($rootSource, $keyedSet)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }
}
