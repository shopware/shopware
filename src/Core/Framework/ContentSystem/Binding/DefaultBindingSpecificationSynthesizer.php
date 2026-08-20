<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * Scans a type file's raw parsed `properties` map for `resolvedBy` keys and synthesizes at most one binding
 * specification for the file: the default wiring for every resolvedBy reference property. Reads the raw map
 * before any type-pipeline validation runs, so property-level malformation is its own hard load error naming
 * the file: a non-array property entry, a missing or non-string `type`, a non-reference `type`, the id-length
 * cap, and the two storage-key collision rules. A malformed resolvedBy value (null, a non-string scalar, an
 * empty array, a multi-key map) is a `resolves` entry value like any authored one and fails hard downstream
 * through the shared sugar tiers.
 *
 * @internal
 */
#[Package('framework')]
final class DefaultBindingSpecificationSynthesizer
{
    // Matches the `name` column of `app_content_system_binding_specification`
    // (Migration1782423128AddAppContentSystemBindingSpecificationTable), the persistence target the minted id
    // (the type name) eventually reaches (core/bundle/plugin bindings never persist, but app bindings do).
    public const MAX_ID_LENGTH = 255;

    /**
     * Returns the raw specification-data array for the same denormalize/canonicalize/validate path an authored
     * inline entry goes through, or null when the file declares no `resolvedBy` property.
     *
     * @param array<string, mixed> $data raw parsed YAML of the containing element-type file (properties + meta)
     *
     * @return array<string, mixed>|null
     */
    public function synthesize(array $data, string $type, string $path): ?array
    {
        $properties = $data['properties'] ?? [];
        if (!\is_array($properties)) {
            return null;
        }

        $resolves = [];
        $storageKeys = [];

        foreach ($properties as $propertyKey => $propertyData) {
            if (!\is_array($propertyData)) {
                throw ContentSystemException::bindingSpecificationLoadFailed(
                    $path,
                    \sprintf('property "%s" must be a map, got %s', (string) $propertyKey, get_debug_type($propertyData)),
                );
            }

            if (!\array_key_exists('resolvedBy', $propertyData)) {
                continue;
            }

            $key = (string) $propertyKey;
            $rawType = $propertyData['type'] ?? null;

            if (!\is_string($rawType) || $rawType === '') {
                throw ContentSystemException::bindingSpecificationLoadFailed(
                    $path,
                    \sprintf('property "%s" declares "resolvedBy" but its "type" is missing or not a string; resolvedBy is only valid on a declared reference (FQCN) property', $key),
                );
            }

            if ($this->isNonReferenceType($rawType)) {
                throw ContentSystemException::bindingSpecificationLoadFailed(
                    $path,
                    \sprintf('property "%s" declares "resolvedBy" but its type "%s" is not a reference (FQCN) property; resolvedBy is only valid on a reference property', $key, $rawType),
                );
            }

            $resolvedByValue = $propertyData['resolvedBy'];
            $resolves[$key] = $resolvedByValue;
            $storageKeys[$key] = $this->extractStorageKey($resolvedByValue);
        }

        if ($resolves === []) {
            return null;
        }

        if (\strlen($type) > self::MAX_ID_LENGTH) {
            throw ContentSystemException::bindingSpecificationLoadFailed(
                $path,
                \sprintf('the synthesized default id "%s" (the element type name) exceeds the maximum length of %d characters', $type, self::MAX_ID_LENGTH),
            );
        }

        $this->assertNoStorageKeyCollisions($storageKeys, $properties, $path);

        return [
            'type' => $type,
            'label' => $this->label($data, $type),
            'resolves' => $resolves,
        ];
    }

    /**
     * Mirrors BindingSpecificationCanonicalizer::declaredReferenceFqcn() at the raw-YAML surface: that method
     * treats a primitive type and "object" alike (both yield a null FQCN, so neither is a reference); this reads
     * the same distinction directly off the raw `type` string, before a PropertyType object exists.
     */
    private function isNonReferenceType(string $rawType): bool
    {
        return $rawType === 'object' || \in_array($rawType, PropertyType::PRIMITIVE_TYPES, true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function label(array $data, string $type): string
    {
        $meta = $data['meta'] ?? null;
        $label = \is_array($meta) ? ($meta['label'] ?? null) : null;

        return \is_string($label) ? $label : $type;
    }

    /**
     * The storage key a resolvedBy value names, a defined concept only for the two built-in resolvedBy branches
     * (ResolvedByLoaderBranch): the key under which an element of this type stores the entity id. A bare string
     * names it directly; the single-key loader form and the canonical form name their flat/config "property"
     * value (the built-in loaders' propertyReference config key is "property" by construction) when it is a
     * string. A value wired through any other loader has no single storage key, so this yields null and the
     * caller runs no collision check for that entry: canonicalization and validation remain its only gates.
     */
    private function extractStorageKey(mixed $resolvedByValue): ?string
    {
        if (\is_string($resolvedByValue)) {
            return $resolvedByValue;
        }

        if (!\is_array($resolvedByValue)) {
            return null;
        }

        if (\array_key_exists('loader', $resolvedByValue)) {
            $config = $resolvedByValue['config'] ?? null;
            $property = \is_array($config) ? ($config[ResolvedByLoaderBranch::STORAGE_KEY_CONFIG_KEY] ?? null) : null;

            return \is_string($property) ? $property : null;
        }

        if (\count($resolvedByValue) === 1) {
            foreach ($resolvedByValue as $flatConfig) {
                $property = \is_array($flatConfig) ? ($flatConfig[ResolvedByLoaderBranch::STORAGE_KEY_CONFIG_KEY] ?? null) : null;

                return \is_string($property) ? $property : null;
            }
        }

        return null;
    }

    /**
     * @param array<string, ?string> $storageKeys keyed by the resolvedBy property's own key
     * @param array<string, mixed> $properties the full raw properties map, for the declared-property-key check
     */
    private function assertNoStorageKeyCollisions(array $storageKeys, array $properties, string $path): void
    {
        $seenBy = [];

        foreach ($storageKeys as $propertyKey => $storageKey) {
            if ($storageKey === null) {
                continue;
            }

            if (\array_key_exists($storageKey, $properties)) {
                throw ContentSystemException::bindingSpecificationLoadFailed(
                    $path,
                    \sprintf('the resolvedBy storage key "%s" of property "%s" collides with a declared property key of the same name; choose a different storage key', $storageKey, $propertyKey),
                );
            }

            if (isset($seenBy[$storageKey])) {
                throw ContentSystemException::bindingSpecificationLoadFailed(
                    $path,
                    \sprintf('properties "%s" and "%s" both declare resolvedBy with the storage key "%s"; two resolvedBy properties of one type must not name the same storage key', $seenBy[$storageKey], $propertyKey, $storageKey),
                );
            }

            $seenBy[$storageKey] = $propertyKey;
        }
    }
}
