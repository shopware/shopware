<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * The drift guard between each data loader's declared {@see LoaderConfigSpecification} and its
 * hand-written config serializer. The serializer stays the sole decoding authority; nothing at runtime
 * cross-checks it against the specification, so this one test does — for every source the container
 * registers ({@see DataLoaderProvider::getSources()}), never a hardcoded list, so a loader added without
 * a specification is caught here automatically.
 *
 * Contract points:
 * 1. the specification is well-formed (asserted directly; cheap, and unlike the build-time compiler pass
 *    it checks the container-built instance, not a constructor-less dry-run);
 * 2. a config assembled from the specification decodes through the source's serializer without a
 *    client-defect {@see ContentSystemException} (the `isConfigComplete()` mirror);
 * 3. every key the decoded config's `jsonSerialize()` emits is declared in the specification;
 * 4. every declared key fed a non-default value survives the decode/serialize round-trip, so a
 *    specification key the serializer does not actually read fails here;
 * 5. an undeclared probe key does not survive the round-trip, so no serializer passes unknown input
 *    through into the config it serializes.
 *
 * Known blind spot: a serializer key that is missing from the specification stays invisible here as long
 * as the config omits it at its default value (the module convention), because a config assembled from
 * the specification can only ever feed declared keys. That direction is guarded by the per-loader
 * configSpecification() declarations, not by this test.
 *
 * @internal
 */
#[Package('framework')]
class LoaderConfigSpecificationContractTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('every registered source declares a well-formed config specification')]
    public function testEverySourceSpecificationIsWellFormed(): void
    {
        $sources = $this->dataLoaderProvider()->getSources();
        static::assertNotEmpty($sources, 'No data loader sources are registered in the container.');

        foreach ($sources as $source) {
            $spec = $this->specificationFor($source);

            $names = array_map(static fn (ConfigKeySpecification $key): string => $key->name, $spec->keys);
            static::assertSame(
                array_values(array_unique($names)),
                $names,
                \sprintf('Source "%s" declares a duplicate config key name.', $source),
            );

            foreach ($spec->keys as $key) {
                $label = \sprintf('source "%s", key "%s"', $source, $key->name);

                static::assertNotSame('loader', $key->name, \sprintf('Reserved key name "loader" used in %s.', $label));
                static::assertNotSame('config', $key->name, \sprintf('Reserved key name "config" used in %s.', $label));
                static::assertContains(
                    $key->type,
                    ConfigKeySpecification::TYPES,
                    \sprintf('Unknown declared type "%s" in %s.', $key->type, $label),
                );
                static::assertFalse(
                    $key->required && $key->hasDefault,
                    \sprintf('A required key must not declare a default in %s.', $label),
                );
                static::assertTrue(
                    $this->kindImpliesStringType($key),
                    \sprintf('A propertyReference/entityName key must be typed "string" in %s.', $label),
                );
                static::assertTrue(
                    $this->missingDefaultIsNull($key),
                    \sprintf('A key with hasDefault=false must declare a null default in %s.', $label),
                );
                static::assertTrue(
                    $this->defaultMatchesDeclaredType($key),
                    \sprintf('The non-null default does not match the declared type "%s" in %s.', $key->type, $label),
                );
                static::assertContains(
                    $key->referencedType,
                    ConfigKeySpecification::REFERENCED_TYPES,
                    \sprintf('Unknown declared referenced type "%s" in %s.', $key->referencedType, $label),
                );
                static::assertTrue(
                    $this->referencedTypeIsPlaced($key),
                    \sprintf('A non-propertyReference key must leave referencedType at "string" in %s.', $label),
                );
                static::assertSame(
                    '',
                    $this->mergeViolation($key, $spec),
                    \sprintf('Invalid merge declaration in %s.', $label),
                );
            }
        }
    }

    #[TestDox('a config assembled from each specification decodes without a client-defect exception')]
    public function testEverySourceSpecificationAssemblesADecodableConfig(): void
    {
        $sources = $this->dataLoaderProvider()->getSources();
        static::assertNotEmpty($sources, 'No data loader sources are registered in the container.');

        $serializerProvider = $this->serializerProvider();

        foreach ($sources as $source) {
            $spec = $this->specificationFor($source);
            $config = $this->assembleMinimalConfig($spec, $source);

            try {
                $serializerProvider->decode($source, $config);
            } catch (ContentSystemException $e) {
                static::assertFalse(
                    ContentSystemException::isClientDefect($e),
                    \sprintf(
                        'The specification-assembled config for source "%s" was rejected as a client defect by its '
                        . 'serializer: %s',
                        $source,
                        $e->getMessage(),
                    ),
                );
            }
        }
    }

    #[TestDox('no source serializes a config key that its specification does not declare')]
    public function testNoSourceSerializesAnUndeclaredConfigKey(): void
    {
        $sources = $this->dataLoaderProvider()->getSources();
        static::assertNotEmpty($sources, 'No data loader sources are registered in the container.');

        $serializerProvider = $this->serializerProvider();

        foreach ($sources as $source) {
            $spec = $this->specificationFor($source);
            $config = $this->assembleFullyPopulatedConfig($spec, $source);

            $decoded = $serializerProvider->decode($source, $config);

            $declared = array_map(static fn (ConfigKeySpecification $key): string => $key->name, $spec->keys);
            $undeclared = array_values(array_diff(array_keys($decoded->jsonSerialize()), $declared));

            static::assertSame(
                [],
                $undeclared,
                \sprintf(
                    'Source "%s" serializes config key(s) absent from its specification: %s',
                    $source,
                    implode(', ', $undeclared),
                ),
            );
        }
    }

    #[TestDox('every declared config key fed a non-default value survives the serializer round-trip')]
    public function testEveryDeclaredConfigKeySurvivesTheSerializerRoundTrip(): void
    {
        $sources = $this->dataLoaderProvider()->getSources();
        static::assertNotEmpty($sources, 'No data loader sources are registered in the container.');

        $serializerProvider = $this->serializerProvider();

        foreach ($sources as $source) {
            $spec = $this->specificationFor($source);
            $config = $this->assembleFullyPopulatedConfig($spec, $source);

            $decoded = $serializerProvider->decode($source, $config);

            $missing = array_values(array_diff(array_keys($config), array_keys($decoded->jsonSerialize())));

            static::assertSame(
                [],
                $missing,
                \sprintf(
                    'Source "%s" declares config key(s) its serializer does not read back: %s',
                    $source,
                    implode(', ', $missing),
                ),
            );
        }
    }

    /**
     * `referencedType` defaults to `'string'`, so a `propertyReference` key pointing at a LIST value is
     * silently wrong unless it declares one: the resolver type-checks the stored list against `'string'`,
     * fails, and hands the loader an unresolvable input — for `entity_collection` that is an empty result
     * for every layout, and no unit test of the loader would notice. The roster makes a new
     * `propertyReference` key a conscious edit here rather than an inherited default, and it fails on a key
     * added to an existing source as well as on a whole new source.
     */
    #[TestDox('every declared propertyReference key matches the referenced-value type roster')]
    public function testEveryPropertyReferenceKeyMatchesTheReferencedTypeRoster(): void
    {
        $expected = [
            'breadcrumb.property' => 'string',
            'breadcrumb.referrerCategoryProperty' => 'string',
            'cross_selling.associationOverride' => 'list<string>',
            'cross_selling.property' => 'string',
            'entity.property' => 'string',
            'entity_collection.property' => 'list<string>',
            'navigation.activeProperty' => 'string',
            'product_configurator.productProperty' => 'object',
            'product_listing.associationOverride' => 'list<string>',
            'product_listing.property' => 'string',
            'product_review.associationOverride' => 'list<string>',
            'product_review.property' => 'string',
            'product_search.associationOverride' => 'list<string>',
            'product_search.searchTermProperty' => 'string',
            'product_suggest.associationOverride' => 'list<string>',
            'product_suggest.searchTermProperty' => 'string',
            'test_multi_reference_gating.activeProperty' => 'string',
            'test_multi_reference_gating.property' => 'string',
            'test_multi_reference_gating.secondProperty' => 'string',
            'test_navigation_shaped.activeProperty' => 'string',
        ];

        $actual = [];
        foreach ($this->dataLoaderProvider()->getSources() as $source) {
            foreach ($this->specificationFor($source)->keysOfKind(ConfigKeyKind::PropertyReference) as $key) {
                $actual[$source . '.' . $key->name] = $key->referencedType;
            }
        }

        ksort($actual);

        static::assertSame(
            $expected,
            $actual,
            'The propertyReference roster changed. A key pointing at a list value MUST declare '
            . 'referencedType: \'list<string>\'; a scalar reference stays \'string\'. Decide which this key is, '
            . 'then update this roster.',
        );
    }

    #[TestDox('no source passes an undeclared config key through its serializer')]
    public function testNoSourcePassesAnUndeclaredConfigKeyThroughItsSerializer(): void
    {
        $sources = $this->dataLoaderProvider()->getSources();
        static::assertNotEmpty($sources, 'No data loader sources are registered in the container.');

        $serializerProvider = $this->serializerProvider();

        foreach ($sources as $source) {
            $spec = $this->specificationFor($source);
            $config = $this->assembleFullyPopulatedConfig($spec, $source);
            $config['contractUnknownProbe'] = 'contract-probe-value';

            try {
                $decoded = $serializerProvider->decode($source, $config);
            } catch (ContentSystemException $e) {
                // Rejecting the unknown key outright also proves it is not passed through.
                static::assertTrue(
                    ContentSystemException::isClientDefect($e),
                    \sprintf('Source "%s" failed on an unknown config key with a non-client-defect exception: %s', $source, $e->getMessage()),
                );

                continue;
            }

            static::assertArrayNotHasKey(
                'contractUnknownProbe',
                $decoded->jsonSerialize(),
                \sprintf('Source "%s" passes an undeclared input key through its serializer.', $source),
            );
        }
    }

    /**
     * The `isConfigComplete()` mirror: each required key filled with a type-appropriate dummy, plus each
     * key that declares a default set to that default. A null default is applied as absence, not a literal
     * `null` on the wire: every core serializer treats a present-but-null optional key as a client defect
     * (e.g. NavigationLoaderConfigSerializer::decode() rejects `rootId => null`) and yields the null default
     * only when the key is absent, so the wire form of a null default is omission.
     *
     * @return array<string, mixed>
     */
    private function assembleMinimalConfig(LoaderConfigSpecification $spec, string $source): array
    {
        $config = [];

        foreach ($spec->keys as $key) {
            if ($key->required) {
                $config[$key->name] = $this->dummyValueForType($key, $source);

                continue;
            }

            if ($key->hasDefault && $key->default !== null) {
                $config[$key->name] = $key->default;
            }
        }

        return $config;
    }

    /**
     * A fully-populated config: required keys with a dummy, plus every optional key with a decodable
     * NON-default value, so each config's `jsonSerialize()` actually emits the key (a config omits a key
     * whose value equals its default). This forces the widest set of serialized keys for the
     * subset-of-specification and round-trip-survival checks.
     *
     * @return array<string, mixed>
     */
    private function assembleFullyPopulatedConfig(LoaderConfigSpecification $spec, string $source): array
    {
        $config = [];

        foreach ($spec->keys as $key) {
            $config[$key->name] = $key->required
                ? $this->dummyValueForType($key, $source)
                : $this->nonDefaultValueForKey($key, $source);
        }

        return $config;
    }

    /**
     * A type-appropriate dummy for a required key, derived from its declared type. The value is only
     * required to decode: EntityLoaderConfigSerializer::decode() validates the `entity`/`property` keys as
     * non-empty strings only (not registration), so a plain string decodes cleanly.
     */
    private function dummyValueForType(ConfigKeySpecification $key, string $source): mixed
    {
        return match ($key->type) {
            'string' => 'dummy-value',
            'integer' => 1,
            'number' => 1.5,
            'boolean' => true,
            'list<string>' => ['dummy-item'],
            'map' => ['dummyKey' => 'dummy-value'],
            default => static::fail(\sprintf(
                'No dummy value defined for declared type "%s" (source "%s", key "%s").',
                $key->type,
                $source,
                $key->name,
            )),
        };
    }

    /**
     * A decodable value for an optional key that differs from its declared default, so the config emits the
     * key. Boolean negates the default; the other types use a distinctive value no core loader declares as a
     * default. An integer/number derives from the default so it is guaranteed non-equal.
     */
    private function nonDefaultValueForKey(ConfigKeySpecification $key, string $source): mixed
    {
        return match ($key->type) {
            'string' => 'contract-non-default-value',
            'integer' => \is_int($key->default) ? $key->default + 1 : 1,
            'number' => \is_float($key->default) ? $key->default + 1.0 : 1.5,
            'boolean' => \is_bool($key->default) ? !$key->default : true,
            'list<string>' => ['contract-non-default-item'],
            'map' => ['contractKey' => 'contract-value'],
            default => static::fail(\sprintf(
                'No non-default value defined for declared type "%s" (source "%s", key "%s").',
                $key->type,
                $source,
                $key->name,
            )),
        };
    }

    private function kindImpliesStringType(ConfigKeySpecification $key): bool
    {
        if ($key->kind === ConfigKeyKind::PropertyReference || $key->kind === ConfigKeyKind::EntityName) {
            return $key->type === 'string';
        }

        return true;
    }

    private function referencedTypeIsPlaced(ConfigKeySpecification $key): bool
    {
        if ($key->kind === ConfigKeyKind::PropertyReference) {
            return true;
        }

        return $key->referencedType === 'string';
    }

    /**
     * The empty string means "no violation"; anything else names what is wrong, so the assertion message
     * carries the reason the way the compiler pass's exception message does.
     */
    private function mergeViolation(ConfigKeySpecification $key, LoaderConfigSpecification $spec): string
    {
        if ($key->mergesInto === null) {
            return '';
        }

        if ($key->kind !== ConfigKeyKind::PropertyReference) {
            return \sprintf('only a propertyReference key can merge, this one has kind "%s"', $key->kind->value);
        }

        if ($key->referencedType !== 'list<string>') {
            return \sprintf('a merging key must reference a "list<string>" value, this one references "%s"', $key->referencedType);
        }

        if ($key->mergesInto === $key->name) {
            return 'a key cannot merge into itself';
        }

        foreach ($spec->keys as $candidate) {
            if ($candidate->name !== $key->mergesInto) {
                continue;
            }

            if ($candidate->kind !== ConfigKeyKind::Literal || $candidate->type !== 'list<string>') {
                return \sprintf('the merge target "%s" must be a literal key of type "list<string>"', $candidate->name);
            }

            return '';
        }

        return \sprintf('the merge target "%s" is not declared in the same specification', $key->mergesInto);
    }

    private function missingDefaultIsNull(ConfigKeySpecification $key): bool
    {
        if ($key->hasDefault) {
            return true;
        }

        return $key->default === null;
    }

    private function defaultMatchesDeclaredType(ConfigKeySpecification $key): bool
    {
        if (!$key->hasDefault || $key->default === null) {
            return true;
        }

        return match ($key->type) {
            'string' => \is_string($key->default),
            'integer' => \is_int($key->default),
            'number' => \is_int($key->default) || \is_float($key->default),
            'boolean' => \is_bool($key->default),
            'list<string>' => \is_array($key->default) && array_is_list($key->default) && $this->allStrings($key->default),
            'map' => \is_array($key->default),
            default => false,
        };
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function allStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (!\is_string($value)) {
                return false;
            }
        }

        return true;
    }

    private function specificationFor(string $source): LoaderConfigSpecification
    {
        return $this->dataLoaderProvider()->get($source)->configSpecification();
    }

    private function dataLoaderProvider(): DataLoaderProvider
    {
        $provider = static::getContainer()->get(DataLoaderProvider::class);
        static::assertInstanceOf(DataLoaderProvider::class, $provider);

        return $provider;
    }

    private function serializerProvider(): DataLoaderConfigSerializerProvider
    {
        $provider = static::getContainer()->get(DataLoaderConfigSerializerProvider::class);
        static::assertInstanceOf(DataLoaderConfigSerializerProvider::class, $provider);

        return $provider;
    }
}
