<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * Computes the context available at an element's position: the providers exposed by its ancestors plus,
 * for a top-level element, the root-ambient context the bound source supplies. Section-agnostic: the
 * root-ambient set is passed in (entity assignment yields the page entity; header/footer yield nothing).
 *
 * Public Core service so the diagnostics kernel and the future mutation operations share one context walk.
 */
#[Package('framework')]
class AvailableContextResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $registry,
    ) {
    }

    /**
     * @param list<ContentElement> $tree the layout's root elements
     * @param list<ProvidedContext> $rootContext root-ambient context for top-level elements (broadcast Single)
     *
     * @return list<ProvidedContext>
     */
    public function resolve(string $targetElementId, array $tree, array $rootContext): array
    {
        $location = $this->locate($tree, $targetElementId);

        if ($location === null) {
            return [];
        }

        ['ancestors' => $ancestors, 'topLevel' => $topLevel] = $location;

        $available = $topLevel ? $rootContext : [];

        foreach ($ancestors as $ancestor) {
            foreach ($ancestor->getProvidesContext() as $contextKey => $provider) {
                $fqcn = $this->resolveProvidedFqcn($ancestor->getComponent(), $contextKey);

                if ($fqcn === null) {
                    continue;
                }

                $available[] = new ProvidedContext(
                    contextKey: (string) $contextKey,
                    fqcn: $fqcn,
                    contextType: $provider->type,
                    providerElementId: $ancestor->getId(),
                    distribution: $provider->distributionConfig->getStrategy(),
                );
            }
        }

        return $available;
    }

    /**
     * @param list<ContentElement> $tree
     *
     * @return array{ancestors: list<ContentElement>, topLevel: bool}|null the ancestor path (root..parent), or null if not found
     */
    private function locate(array $tree, string $targetElementId): ?array
    {
        foreach ($tree as $root) {
            if ($root->getId() === $targetElementId) {
                return ['ancestors' => [], 'topLevel' => true];
            }

            $ancestors = $this->search($root, [$root], $targetElementId);

            if ($ancestors !== null) {
                return ['ancestors' => $ancestors, 'topLevel' => false];
            }
        }

        return null;
    }

    /**
     * @param list<ContentElement> $path elements from the root down to and including $element
     *
     * @return list<ContentElement>|null the ancestor path to the target, or null if the target is not below $element
     */
    private function search(ContentElement $element, array $path, string $targetElementId): ?array
    {
        foreach ($element->allSlotElements() as $child) {
            if ($child->getId() === $targetElementId) {
                return $path;
            }

            $deeper = $this->search($child, [...$path, $child], $targetElementId);

            if ($deeper !== null) {
                return $deeper;
            }
        }

        return null;
    }

    private function resolveProvidedFqcn(string $component, string $contextKey): ?string
    {
        if (!$this->registry->has($component)) {
            return null;
        }

        $properties = $this->registry->get($component)->properties();

        if (!isset($properties[$contextKey])) {
            return null;
        }

        $type = $properties[$contextKey]->type();

        if ($type->isPrimitive()) {
            return null;
        }

        return $type->type();
    }
}
