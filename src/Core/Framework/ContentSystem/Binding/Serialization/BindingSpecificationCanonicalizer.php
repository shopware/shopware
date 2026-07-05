<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Serialization;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderMapResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * Rewrites a {@see BindingSpecificationDto}'s sugared `resolves` entries into canonical `{loader, config}` form and
 * synthesizes its `inputs`, driven entirely by the loaders' declared config specifications, so no rule names a
 * loader source. Runs between denormalization and constraint validation, so
 * `WellFormedBindingSpecification`/`TypeConsistentBindingSpecification` only ever validate canonical shapes. Sugar
 * that cannot expand deterministically is a load-time error carrying the mechanical fix, never a silent pass-through
 * or best guess.
 *
 * @internal
 */
#[Package('framework')]
final class BindingSpecificationCanonicalizer
{
    public function __construct(
        private readonly AbstractContentSystemElementTypeRegistry $typeRegistry,
        private readonly AbstractContentSystemDataLoaderMapResolver $mapResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SalesChannelDefinitionInstanceRegistry $salesChannelDefinitionRegistry,
    ) {
    }

    /**
     * Returns a new DTO whose `resolves` entries are canonical and whose `inputs` are synthesized from the
     * canonical wiring and stamped with the derived `required` flag. `$id` identifies the specification in
     * error messages. `type` and `label` pass through unchanged.
     *
     * `$typeOverlay` (keyed by type name) is consulted before the registry when resolving the declared type. It
     * carries types not yet in the registry: an app's own types at install/validate time, when the app is still
     * inactive so neither `DatabaseTypeLoader` (WHERE active = 1) nor the compiler pass surfaces them. An empty
     * overlay (every non-app path) resolves against the registry alone, unchanged.
     *
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay
     */
    public function canonicalize(BindingSpecificationDto $dto, string $id, array $typeOverlay = []): BindingSpecificationDto
    {
        $type = $this->registeredType($dto->type, $id, $typeOverlay);

        $this->rejectAuthoredRequired($dto, $id);

        $map = $this->mapResolver->resolve();

        if (!\is_array($dto->resolves)) {
            // A null resolves has nothing to canonicalize; a non-array resolves is a shape violation
            // WellFormedBindingSpecification rejects downstream with the precise message. With no canonical
            // entry, nothing is synthesized and no input is required, but every explicit input still carries
            // its (false) derived flag.
            return new BindingSpecificationDto($dto->type, $dto->label, $dto->resolves, $this->buildInputs($dto->inputs, [], $type, $map), $dto->promoted);
        }

        $canonical = [];
        foreach ($dto->resolves as $key => $entry) {
            $canonical[(string) $key] = $this->canonicalizeEntry((string) $key, $entry, $type, $map, $id);
        }

        return new BindingSpecificationDto($dto->type, $dto->label, $canonical, $this->buildInputs($dto->inputs, $canonical, $type, $map), $dto->promoted);
    }

    /**
     * Authoring `required:` inside an inputs entry is a load-time error: the flag is derived from the wiring, so
     * an authored copy would drift. Runs before any stamping, on the raw incoming inputs.
     */
    private function rejectAuthoredRequired(BindingSpecificationDto $dto, string $id): void
    {
        if (!\is_array($dto->inputs)) {
            return;
        }

        foreach ($dto->inputs as $key => $entry) {
            if (\is_array($entry) && \array_key_exists('required', $entry)) {
                throw ContentSystemException::bindingSpecificationCanonicalizationFailed(
                    $id,
                    \sprintf('inputs entry "%s" declares "required", but the flag is derived from the wiring and must not be authored; remove it.', (string) $key),
                );
            }
        }
    }

    /**
     * The new inputs facet: a synthesized entry per primitive-property `propertyReference` across the canonical
     * `resolves` entries (explicit entries always win, keeping their default), with the derived `required` flag
     * stamped into every entry. A non-array, non-null inputs value passes through untouched for WellFormed to reject.
     *
     * @param array<string, array<array-key, mixed>> $canonical
     */
    private function buildInputs(mixed $inputs, array $canonical, ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map): mixed
    {
        if ($inputs !== null && !\is_array($inputs)) {
            return $inputs;
        }

        $explicit = \is_array($inputs) ? $inputs : [];

        [$synthesizable, $required] = $this->deriveInputFacts($canonical, $type, $map);

        $result = [];
        foreach (array_keys($synthesizable) as $property) {
            $result[$property] = [];
        }

        foreach ($explicit as $key => $entry) {
            $result[(string) $key] = $entry;
        }

        foreach ($result as $key => $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $entry['required'] = isset($required[$key]);
            $result[$key] = $entry;
        }

        return $result;
    }

    /**
     * Walks every canonical `resolves` entry and, per `propertyReference` config key naming a primitive property,
     * marks that property synthesizable, and required when the config key is required and the reference property
     * is declared `required: true`. A non-string or non-primitive configured value is filtered out here (the
     * generalized validator rejects authored specs with that defect); an unregistered loader source is skipped
     * (TypeConsistent reports it).
     *
     * @param array<string, array<array-key, mixed>> $canonical
     *
     * @return array{array<string, true>, array<string, true>}
     */
    private function deriveInputFacts(array $canonical, ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map): array
    {
        $synthesizable = [];
        $required = [];

        foreach ($canonical as $refKey => $entry) {
            $source = $entry['loader'] ?? null;
            $config = $entry['config'] ?? null;

            if (!\is_string($source) || !\is_array($config)) {
                continue;
            }

            if (!isset($map->sourceToConfigSpecifications[$source])) {
                continue;
            }

            $referenceRequired = $this->isReferenceRequired($type, (string) $refKey);

            foreach ($map->configSpecificationFor($source)->keys as $configKey) {
                if ($configKey->kind !== ConfigKeyKind::PropertyReference) {
                    continue;
                }

                $configured = $config[$configKey->name] ?? null;

                if (!\is_string($configured) || !$this->isPrimitiveProperty($type, $configured)) {
                    continue;
                }

                $synthesizable[$configured] = true;

                if ($configKey->required && $referenceRequired) {
                    $required[$configured] = true;
                }
            }
        }

        return [$synthesizable, $required];
    }

    private function isReferenceRequired(ContentSystemElementTypeSpecification $type, string $refKey): bool
    {
        $property = $type->properties()[$refKey] ?? null;

        return $property instanceof PropertySpecification && $property->required();
    }

    private function isPrimitiveProperty(ContentSystemElementTypeSpecification $type, string $name): bool
    {
        $property = $type->properties()[$name] ?? null;

        return $property instanceof PropertySpecification && $property->type()->isPrimitive();
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay
     */
    private function registeredType(mixed $type, string $id, array $typeOverlay): ContentSystemElementTypeSpecification
    {
        if (\is_string($type) && isset($typeOverlay[$type])) {
            return $typeOverlay[$type];
        }

        if (!\is_string($type) || $type === '' || !$this->typeRegistry->has($type)) {
            throw ContentSystemException::bindingSpecificationUnknownType($id, \is_string($type) ? $type : get_debug_type($type));
        }

        return $this->typeRegistry->get($type);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function canonicalizeEntry(string $refKey, mixed $entry, ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map, string $id): array
    {
        if (\is_string($entry)) {
            return $this->expandTierA($refKey, $entry, $type, $map, $id);
        }

        if (!\is_array($entry)) {
            throw $this->unrecognizedShape($refKey, $id);
        }

        if (\array_key_exists('loader', $entry)) {
            $this->assertNoMixedLoaderSource($refKey, $entry, $map, $id);

            return $entry;
        }

        if (\count($entry) === 1) {
            foreach ($entry as $onlyKey => $flatConfig) {
                $source = (string) $onlyKey;

                if (isset($map->sourceToConfigSpecifications[$source]) && \is_array($flatConfig)) {
                    return $this->expandTierB($refKey, $source, $flatConfig, $type, $map, $id);
                }
            }
        }

        throw $this->unrecognizedShape($refKey, $id);
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function assertNoMixedLoaderSource(string $refKey, array $entry, ContentSystemDataLoaderMap $map, string $id): void
    {
        foreach (array_keys($entry) as $key) {
            if ($key === 'loader') {
                continue;
            }

            if (isset($map->sourceToConfigSpecifications[(string) $key])) {
                throw ContentSystemException::bindingSpecificationCanonicalizationFailed(
                    $id,
                    \sprintf('resolves entry "%s" mixes the canonical "loader" form with the loader-source key "%s"; use either the {loader, config} form or the single-key "%s" form, not both.', $refKey, (string) $key, (string) $key),
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function expandTierA(string $refKey, string $inputProperty, ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map, string $id): array
    {
        $fqcn = $this->declaredReferenceFqcn($type, $refKey);

        if ($fqcn === null) {
            throw ContentSystemException::bindingSpecificationCanonicalizationFailed(
                $id,
                \sprintf('the tier-A shorthand for "%s" needs "%s" to be a declared reference property of type "%s", but it is not; use the single-key loader form (tier B) or the canonical {loader, config} form (tier C).', $refKey, $refKey, $type->name()),
            );
        }

        $eligible = $this->eligibleTierASources($fqcn, $map);

        if (\count($eligible) !== 1) {
            throw ContentSystemException::bindingSpecificationCanonicalizationFailed($id, $this->tierAAmbiguityReason($refKey, $fqcn, array_keys($eligible)));
        }

        $source = (string) array_key_first($eligible);
        $capability = $eligible[$source];

        $config = $capability->configTemplate;
        $config[$this->requiredPropertyReferenceKey($map->configSpecificationFor($source), $id)] = $inputProperty;
        $config = $this->fillEntityNameKeys($source, $config, $type, $refKey, $map, $id, $fqcn);

        return ['loader' => $source, 'config' => $config];
    }

    /**
     * @param array<array-key, mixed> $flatConfig
     *
     * @return array<string, mixed>
     */
    private function expandTierB(string $refKey, string $source, array $flatConfig, ContentSystemElementTypeSpecification $type, ContentSystemDataLoaderMap $map, string $id): array
    {
        $specification = $map->configSpecificationFor($source);
        $declaredKeys = array_map(static fn (ConfigKeySpecification $key): string => $key->name, $specification->keys);

        foreach (array_keys($flatConfig) as $configKey) {
            if (\in_array((string) $configKey, $declaredKeys, true)) {
                continue;
            }

            throw ContentSystemException::bindingSpecificationCanonicalizationFailed(
                $id,
                \sprintf('the loader "%s" does not declare the config key "%s" used for "%s"; declared keys are: %s.', $source, (string) $configKey, $refKey, implode(', ', $declaredKeys)),
            );
        }

        $config = $this->fillEntityNameKeys($source, $flatConfig, $type, $refKey, $map, $id, null);

        return ['loader' => $source, 'config' => $config];
    }

    /**
     * Every required `entityName`-kind key absent from the config is filled by FQCN derivation. The reference
     * FQCN is resolved lazily and only when a fill is actually needed, so a tier-B config that authors all its
     * entity keys explicitly never depends on the reference property being a resolvable reference.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function fillEntityNameKeys(string $source, array $config, ContentSystemElementTypeSpecification $type, string $refKey, ContentSystemDataLoaderMap $map, string $id, ?string $fqcn): array
    {
        $resolvedFqcn = $fqcn;

        foreach ($map->configSpecificationFor($source)->keys as $key) {
            if (!$key->required || $key->kind !== ConfigKeyKind::EntityName) {
                continue;
            }

            if (\array_key_exists($key->name, $config)) {
                continue;
            }

            $resolvedFqcn ??= $this->declaredReferenceFqcn($type, $refKey);

            if ($resolvedFqcn === null) {
                throw ContentSystemException::bindingSpecificationCanonicalizationFailed(
                    $id,
                    \sprintf('cannot derive the entity name for the "%s" config key of loader "%s": "%s" is not a declared reference property of type "%s"; author the entity name explicitly.', $key->name, $source, $refKey, $type->name()),
                );
            }

            $config[$key->name] = $this->deriveEntityName($resolvedFqcn, $refKey, $id);
        }

        return $config;
    }

    /**
     * The eligible tier-A loader sources keyed by source, each mapped to its exact-match capability. A source is
     * eligible iff it exposes a capability whose produced type equals the FQCN exactly (not `is_a`; subtype
     * fan-out is the ambiguity this refuses to resolve), its config specification has exactly one required
     * `propertyReference` key (the string fills it), and every other required key is either covered by that
     * capability's template or is of kind `entityName` (fillable by FQCN derivation).
     *
     * @return array<string, LoaderTypeCapability>
     */
    private function eligibleTierASources(string $fqcn, ContentSystemDataLoaderMap $map): array
    {
        $eligible = [];

        foreach ($map->sourceToCapabilities as $source => $capabilities) {
            $capability = $this->exactMatchCapability($capabilities, $fqcn);

            if ($capability === null) {
                continue;
            }

            $specification = $map->configSpecificationFor($source);

            if (!$this->hasExactlyOneRequiredPropertyReference($specification)) {
                continue;
            }

            if (!$this->requiredResidueFillable($specification, $capability)) {
                continue;
            }

            $eligible[$source] = $capability;
        }

        return $eligible;
    }

    /**
     * @param list<LoaderTypeCapability> $capabilities
     */
    private function exactMatchCapability(array $capabilities, string $fqcn): ?LoaderTypeCapability
    {
        foreach ($capabilities as $capability) {
            if ($capability->producedType === $fqcn) {
                return $capability;
            }
        }

        return null;
    }

    private function hasExactlyOneRequiredPropertyReference(LoaderConfigSpecification $specification): bool
    {
        $count = 0;

        foreach ($specification->keys as $key) {
            if ($key->required && $key->kind === ConfigKeyKind::PropertyReference) {
                ++$count;
            }
        }

        return $count === 1;
    }

    private function requiredResidueFillable(LoaderConfigSpecification $specification, LoaderTypeCapability $capability): bool
    {
        foreach ($specification->keys as $key) {
            if (!$key->required || $key->kind === ConfigKeyKind::PropertyReference) {
                continue;
            }

            if (\array_key_exists($key->name, $capability->configTemplate)) {
                continue;
            }

            if ($key->kind === ConfigKeyKind::EntityName) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function requiredPropertyReferenceKey(LoaderConfigSpecification $specification, string $id): string
    {
        foreach ($specification->keys as $key) {
            if ($key->required && $key->kind === ConfigKeyKind::PropertyReference) {
                return $key->name;
            }
        }

        // Unreachable: eligibility already established exactly one such key on the selected source.
        throw ContentSystemException::bindingSpecificationCanonicalizationFailed($id, 'internal: no required propertyReference key on an eligible tier-A source');
    }

    private function deriveEntityName(string $fqcn, string $refKey, string $id): string
    {
        $definitions = $this->definitionRegistry->getDefinitions();
        $salesChannelDefinitions = $this->salesChannelDefinitionRegistry->getSalesChannelDefinitions();

        $matches = [];
        foreach ($definitions as $definition) {
            if ($definition instanceof MappingEntityDefinition) {
                continue;
            }

            if ($definition->getEntityClass() === ArrayEntity::class) {
                continue;
            }

            $entityName = $definition->getEntityName();
            $producedClass = isset($salesChannelDefinitions[$entityName])
                ? $salesChannelDefinitions[$entityName]->getEntityClass()
                : $definition->getEntityClass();

            if ($producedClass === $fqcn) {
                $matches[] = $entityName;
            }
        }

        if (\count($matches) === 1) {
            return $matches[0];
        }

        $reason = $matches === []
            ? \sprintf('no registered entity produces "%s" for reference "%s"; author the entity name explicitly.', $fqcn, $refKey)
            : \sprintf('multiple registered entities (%s) produce "%s" for reference "%s"; author the entity name explicitly.', implode(', ', $matches), $fqcn, $refKey);

        throw ContentSystemException::bindingSpecificationCanonicalizationFailed($id, $reason);
    }

    /**
     * The declared reference FQCN for a type's property, or null when the key is not a declared property or the
     * property is not a single-FQCN reference (primitive, union, or `object`). Mirrors the derivation
     * {@see \Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics} uses.
     */
    private function declaredReferenceFqcn(ContentSystemElementTypeSpecification $type, string $key): ?string
    {
        $property = $type->properties()[$key] ?? null;

        if (!$property instanceof PropertySpecification) {
            return null;
        }

        $propertyType = $property->type();
        $declaredType = $propertyType->type();

        if ($propertyType->isPrimitive() || !\is_string($declaredType) || $declaredType === 'object') {
            return null;
        }

        return $declaredType;
    }

    /**
     * @param list<string> $eligibleSources
     */
    private function tierAAmbiguityReason(string $refKey, string $fqcn, array $eligibleSources): string
    {
        if ($eligibleSources === []) {
            return \sprintf('no loader source can produce "%s" from a single property reference for tier-A shorthand "%s"; use the single-key loader form (tier B) with the loader named explicitly.', $fqcn, $refKey);
        }

        return \sprintf('multiple loader sources (%s) can produce "%s" for tier-A shorthand "%s"; use the single-key loader form (tier B) to name the loader explicitly.', implode(', ', $eligibleSources), $fqcn, $refKey);
    }

    private function unrecognizedShape(string $refKey, string $id): ContentSystemException
    {
        return ContentSystemException::bindingSpecificationCanonicalizationFailed(
            $id,
            \sprintf('resolves entry "%s" is not a recognized shape; accepted shapes are a property-reference string (tier A), a single-key map whose key is a loader source (tier B), or a map with a "loader" key and a "config" map (tier C).', $refKey),
        );
    }
}
