<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout;

use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * The single place a layout write is admitted. Every tree on its way into `content_layout` passes through
 * {@see apply()}, whichever route built it — the mutations, a plain DAL write, the Sync API, an import or a
 * fixture — so a rule about what a stored tree looks like is stated once here instead of once per route.
 *
 * Three passes run in a fixed order. Type defaults are seeded first, so the style pass and the attribution
 * pass both see the properties a stored element will actually carry. Style is canonicalised next, against
 * the option registry. Attribution is reconciled last, because it compares stored wiring against the
 * specification that claims it and must judge the wiring as it will be stored.
 *
 * Nothing mutates: each pass hands back a new forest built through {@see StoredElement}'s `with*()` methods.
 *
 * @internal
 */
#[Package('framework')]
final class LayoutWriteBoundary
{
    public function __construct(
        private readonly LayoutDefaultSeeder $defaultSeeder,
        private readonly StoredTreeStyleNormalizer $styleNormalizer,
        private readonly AttributionReconciler $attributionReconciler,
    ) {
    }

    public function apply(StoredTree $tree): StoredTree
    {
        $seeded = $this->elements($this->defaultSeeder->seed($tree->roots));

        $styled = $this->styleNormalizer->normalize(new StoredTree($seeded))->roots;

        return new StoredTree($this->elements($this->attributionReconciler->reconcile($styled)));
    }

    /**
     * The seeder and the reconciler declare `list<StoredElement>` on both ends; this rejects a collaborator
     * that hands back anything else, so a node the stored tree cannot hold never reaches storage.
     *
     * @param list<mixed> $forest
     *
     * @return list<StoredElement>
     */
    private function elements(array $forest): array
    {
        $elements = [];

        foreach ($forest as $node) {
            if (!$node instanceof StoredElement) {
                throw ContentSystemException::invalidFieldValueType('layout', StoredElement::class, get_debug_type($node));
            }

            $elements[] = $node;
        }

        return $elements;
    }
}
