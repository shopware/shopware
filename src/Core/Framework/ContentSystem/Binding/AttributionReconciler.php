<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Re-derives each element's {@see StoredElement::$attributedSpecifications} at the DAL write boundary.
 * A dropped attribution (diverged wiring, missing specification, or missing binding) is never an error.
 *
 * Honesty comparison correctness depends on every config serializer honoring its round-trip contract
 * ({@see AbstractContentDataLoaderConfigSerializer}): `encode(decode($x))` must be stable and equal to
 * `decode($x)->jsonSerialize()`. A serializer that normalizes or coerces values on decode, or whose
 * `encode` diverges from `jsonSerialize`, would silently drop an attribution that is in fact still honest.
 *
 * Only a {@see ContentSystemException} whose code is a client defect
 * ({@see ContentSystemException::isClientDefect()}) is caught and treated as "not honest"; every other
 * exception, including a registry-build failure, propagates.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class AttributionReconciler
{
    /**
     * Per-reconcile() memo of the specification side of the honesty comparison, keyed by "specificationId:key".
     *
     * @var array<string, array{source: string, encoded: array<int|string, mixed>}|null>
     */
    private array $specWiringCache = [];

    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $registry,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly ConfigCanonicalizer $configCanonicalizer,
    ) {
    }

    /**
     * @param list<StoredElement> $forest
     *
     * @return list<StoredElement>
     */
    public function reconcile(array $forest): array
    {
        $this->specWiringCache = [];

        $reconciled = [];

        foreach ($forest as $element) {
            $reconciled[] = $this->reconcileElement($element);
        }

        return $reconciled;
    }

    private function reconcileElement(StoredElement $element): StoredElement
    {
        if ($element->slots === [] && $element->attributedSpecifications === []) {
            return $element;
        }

        $slots = [];
        foreach ($element->slots as $name => $children) {
            $slots[$name] = array_map($this->reconcileElement(...), $children);
        }

        return $element
            ->withSlots($slots)
            ->withAttributedSpecifications($this->reconcileElementAttributions($element));
    }

    /**
     * @return array<string, string>
     */
    private function reconcileElementAttributions(StoredElement $element): array
    {
        $attributions = $element->attributedSpecifications;

        if ($attributions === []) {
            return [];
        }

        $dataRequirements = $element->dataRequirements;
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
     * The specification side of the honesty comparison: the binding's source plus its canonicalized encoded
     * config, or null when the specification or its binding for $key no longer exists.
     *
     * @return array{source: string, encoded: array<int|string, mixed>}|null
     */
    private function specWiring(string $specificationId, string $key): ?array
    {
        $cacheKey = $specificationId . ':' . $key;

        if (\array_key_exists($cacheKey, $this->specWiringCache)) {
            return $this->specWiringCache[$cacheKey];
        }

        $specification = $this->registry->get($specificationId);

        if ($specification === null) {
            return $this->specWiringCache[$cacheKey] = null;
        }

        $binding = $specification->resolves()[$key] ?? null;

        if ($binding === null) {
            return $this->specWiringCache[$cacheKey] = null;
        }

        $source = $binding->loader;
        $configObject = $this->configSerializerProvider->decode($source, $binding->config);
        $encoded = $this->configCanonicalizer->canonicalize($this->configSerializerProvider->encode($source, $configObject));

        return $this->specWiringCache[$cacheKey] = ['source' => $source, 'encoded' => $encoded];
    }
}
