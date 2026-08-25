<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Field\StoredElementListFieldSerializer;
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
 * Nothing here converts this exception into "not honest" — it is intercepted only to attach the element id
 * before re-throwing. A wiring the comparison cannot even encode — an element whose requirement source
 * has no registered config serializer, which raises
 * {@see ContentSystemException::configSerializerNotRegistered()} — is a wiring the write cannot judge, not
 * an unattributed element, so it escapes and refuses the write rather than being recorded as "not honest".
 * {@see StoredElementListFieldSerializer::normalize()}
 * remaps it, like every other {@see ContentSystemException} raised under the write boundary, to a
 * `WriteConstraintViolationException` carrying that error code, so the caller is told what was wrong with the
 * payload.
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

            try {
                $honest = $this->isHonestForElement($specificationId, $key, $requirement);
            } catch (ContentSystemException $exception) {
                throw $this->withElementId($exception, $element->id);
            }

            if ($honest) {
                $filtered[$key] = $specificationId;
            }
        }

        return $filtered;
    }

    /**
     * Re-throws a CONFIG_SERIALIZER_NOT_REGISTERED fault carrying the element whose wiring named the
     * unregistered source, so the caller can see which element to fix. The caught exception's own "source"
     * parameter is read back rather than re-derived, since the fault can originate from either the element's
     * own requirement source ({@see isHonestForElement}) or the specification's binding source
     * ({@see specWiring}). Every other ContentSystemException is returned unchanged.
     */
    private function withElementId(ContentSystemException $exception, string $elementId): ContentSystemException
    {
        if ($exception->getErrorCode() !== ContentSystemException::CONFIG_SERIALIZER_NOT_REGISTERED) {
            return $exception;
        }

        $source = $exception->getParameter('source');
        if (!\is_string($source)) {
            return $exception;
        }

        return ContentSystemException::configSerializerNotRegistered($source, $elementId);
    }

    private function isHonestForElement(string $specificationId, string $key, DataRequirement $requirement): bool
    {
        $specWiring = $this->specWiring($specificationId, $key);

        if ($specWiring === null) {
            return false;
        }

        $elementEncoded = $this->configCanonicalizer->canonicalize(
            $this->configSerializerProvider->encode($requirement->source, $requirement->config)
        );

        return $specWiring['source'] === $requirement->source && $specWiring['encoded'] === $elementEncoded;
    }

    /**
     * The specification side of the honesty comparison: the binding's source plus its canonicalized encoded
     * config, or null when the specification or its binding for $key no longer exists.
     *
     * Null is an answer, not a missing collaborator: an attribution names a specification the registry
     * assembles at runtime from element-type directories and active app rows, and no write gate checks that
     * name — {@see StoredElementCodec} decodes an
     * `attributedSpecifications` entry on its shape alone. An uninstalled plugin, a deactivated app, a
     * specification that dropped the key in a new version, or a caller who simply sent an unknown id all
     * leave a live attribution with nothing behind it, and "nothing claims this wiring" is exactly the
     * comparison's negative result.
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
