<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Immutability guard shared by the mutation-op tests. A {@see ContentElement} extends Struct and is mutable
 * (`setProperty`, `setProperties`, `setContextDefinitions`, in-place slot edits), so a "does not mutate input"
 * test that only inspects a single getter or a count would still pass if an operation mutated a node's
 * properties or slot children in place. {@see snapshotTree()} captures the full structural state of an input
 * tree before `apply()` runs, and {@see assertInputTreeUnmutated()} re-captures it afterwards and asserts the
 * two are identical, covering every node's id, component, properties, data requirements, context definitions,
 * and slot children recursively. Leaf value objects are held by reference, so the strict comparison detects a
 * swapped object as well as a changed scalar.
 *
 * @internal
 *
 * @phpstan-require-extends TestCase
 */
#[Package('framework')]
trait AssertsImmutableInput
{
    /**
     * @param list<ContentElement> $tree
     *
     * @return list<array<string, mixed>>
     */
    protected function snapshotTree(array $tree): array
    {
        return array_map($this->snapshotElement(...), $tree);
    }

    /**
     * @param list<array<string, mixed>> $snapshotBefore
     * @param list<ContentElement> $treeAfter
     */
    protected function assertInputTreeUnmutated(array $snapshotBefore, array $treeAfter): void
    {
        static::assertSame(
            $snapshotBefore,
            $this->snapshotTree($treeAfter),
            'The mutation operation mutated its input tree in place.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotElement(ContentElement $element): array
    {
        $slots = [];
        foreach ($element->getSlots() as $slotName => $slotContent) {
            $slots[$slotName] = array_map($this->snapshotElement(...), array_values($slotContent->getElements()));
        }

        return [
            'id' => $element->getId(),
            'component' => $element->getComponent(),
            'properties' => $element->getProperties(),
            'dataRequirements' => $element->getDataRequirements(),
            'contextDefinitions' => $element->getContextDefinitions(),
            'providesContext' => $element->getProvidesContext(),
            'acceptsContext' => $element->getAcceptsContext(),
            'slots' => $slots,
        ];
    }
}
