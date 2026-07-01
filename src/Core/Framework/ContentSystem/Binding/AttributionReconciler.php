<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\Log\Package;

/**
 * Reconciles each element's `attributedSpecifications` against its current wiring at the DAL write boundary, so
 * a persisted attribution is honest by construction: `attributedSpecifications[$key] => $specId` survives a write
 * only while the element's current `source`/`config` for `$key` still equals what `$specId`'s binding for `$key`
 * produces. A key whose wiring has since diverged (or whose specification/binding no longer exists) is dropped,
 * never flagged as an error — a user who edits a key's wiring away from the specification simply loses that
 * key's attribution; every other key keeps its own independently. This per-key drop is the only silent
 * outcome: it is reached by looking up a resolved specification (via {@see
 * AbstractContentSystemBindingSpecificationRegistry::get()}) and finding its wiring for `$key` diverged or
 * gone. If the registry's own build fails instead — a broken authored binding specification makes `get()`/
 * `all()` throw — that exception is NOT absorbed here: only a {@see ContentSystemException} whose code is a
 * client defect ({@see ContentSystemException::isClientDefect()}) is caught and treated as "not honest";
 * every other exception, including a registry-build failure, propagates out of the reconciler.
 *
 * Mirrors {@see LayoutDefaultSeeder}: it walks the same write-time
 * element forest, handles both a hydrated {@see ContentElement} and a raw element array (Admin / Sync JSON), and
 * recurses every slot's children.
 *
 * The honesty comparison encodes both the element's wiring and the specification's binding through the
 * loader's config serializer and compares the canonicalized results, so it is only correct while every
 * config serializer honors its round-trip contract
 * ({@see AbstractContentDataLoaderConfigSerializer}):
 * `encode(decode($x))` must be stable and equal to `decode($x)->jsonSerialize()`. A serializer that
 * normalizes or coerces values on decode, or whose encode diverges from jsonSerialize, would drop an
 * attribution that is in fact still honest.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributionReconciler
{
    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly ConfigCanonicalizer $configCanonicalizer,
    ) {
    }

    /**
     * @param list<mixed> $forest
     *
     * @return list<mixed>
     */
    public function reconcile(array $forest): array
    {
        $reconciled = [];

        foreach ($forest as $node) {
            $reconciled[] = $this->reconcileNode($node);
        }

        return $reconciled;
    }

    private function reconcileNode(mixed $node): mixed
    {
        if ($node instanceof ContentElement) {
            return $this->reconcileElement($node);
        }

        if (\is_array($node)) {
            return $this->reconcileArray($node);
        }

        return $node;
    }

    private function reconcileElement(ContentElement $element): ContentElement
    {
        $slots = [];
        foreach ($element->getSlots() as $name => $slotContent) {
            $children = [];
            foreach ($slotContent as $child) {
                $children[] = $this->reconcileElement($child);
            }
            $slots[$name] = new SlotContent($children);
        }

        return new ContentElement(
            $element->getId(),
            $element->getComponent(),
            $element->getDataRequirements(),
            $element->getProperties(),
            $slots,
            $element->getContextDefinitions(),
            $element->getStyle(),
            $this->reconcileElementAttributions($element),
        );
    }

    /**
     * @return array<string, string>
     */
    private function reconcileElementAttributions(ContentElement $element): array
    {
        $attributions = $element->getAttributedSpecifications();

        if ($attributions === []) {
            return [];
        }

        $dataRequirements = $element->getDataRequirements();
        $filtered = [];

        foreach ($attributions as $key => $specificationId) {
            $requirement = $dataRequirements[$key] ?? null;

            if ($requirement === null) {
                continue;
            }

            if ($this->isHonestForElement($specificationId, $key, $requirement)) {
                $filtered[$key] = $specificationId;
            }
        }

        return $filtered;
    }

    private function isHonestForElement(string $specificationId, string $key, DataRequirement $requirement): bool
    {
        try {
            $specWiring = $this->specWiring($specificationId, $key);

            if ($specWiring === null) {
                return false;
            }

            $elementEncoded = $this->configCanonicalizer->canonicalize(
                $this->configSerializerProvider->encode($requirement->source, $requirement->config)
            );

            return $specWiring['source'] === $requirement->source && $specWiring['encoded'] === $elementEncoded;
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    private function reconcileArray(array $node): array
    {
        $slots = $node['slots'] ?? null;

        if (\is_array($slots)) {
            $node['slots'] = array_map($this->reconcileSlotChildren(...), $slots);
        }

        $attributions = $node['attributedSpecifications'] ?? null;

        if (!\is_array($attributions) || $attributions === []) {
            return $node;
        }

        $dataRequirements = $node['dataRequirements'] ?? [];
        $dataRequirements = \is_array($dataRequirements) ? $dataRequirements : [];

        $filtered = $this->reconcileRawAttributions($attributions, $dataRequirements);

        if ($filtered === []) {
            unset($node['attributedSpecifications']);

            return $node;
        }

        $node['attributedSpecifications'] = $filtered;

        return $node;
    }

    private function reconcileSlotChildren(mixed $children): mixed
    {
        if (!\is_array($children)) {
            return $children;
        }

        return array_map($this->reconcileNode(...), $children);
    }

    /**
     * @param array<array-key, mixed> $attributions
     * @param array<array-key, mixed> $dataRequirements
     *
     * @return array<string, string>
     */
    private function reconcileRawAttributions(array $attributions, array $dataRequirements): array
    {
        $filtered = [];

        foreach ($attributions as $key => $specificationId) {
            if (!\is_string($key) || !\is_string($specificationId)) {
                continue;
            }

            $requirementEntry = $dataRequirements[$key] ?? null;

            if (!\is_array($requirementEntry)) {
                continue;
            }

            if ($this->isHonestForRaw($specificationId, $key, $requirementEntry)) {
                $filtered[$key] = $specificationId;
            }
        }

        return $filtered;
    }

    /**
     * @param array<array-key, mixed> $requirementEntry
     */
    private function isHonestForRaw(string $specificationId, string $key, array $requirementEntry): bool
    {
        $elementSource = $requirementEntry['source'] ?? null;

        if (!\is_string($elementSource)) {
            return false;
        }

        $elementConfigData = $requirementEntry['config'] ?? [];
        $elementConfigData = \is_array($elementConfigData) ? $elementConfigData : [];

        try {
            $specWiring = $this->specWiring($specificationId, $key);

            if ($specWiring === null) {
                return false;
            }

            $elementConfigObject = $this->configSerializerProvider->decode($elementSource, $elementConfigData);
            $elementEncoded = $this->configCanonicalizer->canonicalize(
                $this->configSerializerProvider->encode($elementSource, $elementConfigObject)
            );

            return $specWiring['source'] === $elementSource && $specWiring['encoded'] === $elementEncoded;
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * The specification side of the honesty comparison: the binding's source plus its canonicalized encoded
     * config, or null when the specification or its binding for $key no longer exists.
     *
     * @return array{source: string, encoded: array<int|string, mixed>}|null
     */
    private function specWiring(string $specificationId, string $key): ?array
    {
        $specification = $this->registry->get($specificationId);

        if ($specification === null) {
            return null;
        }

        $binding = $specification->resolves()[$key] ?? null;

        if ($binding === null) {
            return null;
        }

        $source = $binding->source;
        $configObject = $this->configSerializerProvider->decode($source, $binding->config);
        $encoded = $this->configCanonicalizer->canonicalize($this->configSerializerProvider->encode($source, $configObject));

        return ['source' => $source, 'encoded' => $encoded];
    }
}
