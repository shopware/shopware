<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoaderInputResolver::class)]
class LoaderInputResolverTest extends TestCase
{
    /**
     * @param array{hasDefault?: bool, default?: mixed} $keySpecOptions
     */
    #[DataProvider('literalKeyResolutionProvider')]
    #[TestDox('resolves a literal key: $_dataName')]
    public function testLiteralKeyResolution(?string $configuredValue, array $keySpecOptions, mixed $expected): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('label', ConfigKeyKind::Literal, 'string', false, ...$keySpecOptions),
        ]);

        $inputs = (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(label: $configuredValue), []);

        static::assertSame($expected, $inputs->get('label'));
    }

    /**
     * @return iterable<string, array{?string, array{hasDefault?: bool, default?: mixed}, mixed}>
     */
    public static function literalKeyResolutionProvider(): iterable
    {
        yield 'configured value takes precedence over the declared default' => [
            'title', ['hasDefault' => true, 'default' => 'headline'], 'title',
        ];

        yield 'falls back to the declared default when the config carries no value' => [
            null, ['hasDefault' => true, 'default' => 'headline'], 'headline',
        ];

        yield 'resolves to null when neither the config nor a declared default carries a value' => [
            null, [], null,
        ];
    }

    #[TestDox('an entityName key takes its configured value without dereferencing it')]
    public function testEntityNameKeyIsNotDereferenced(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
        ]);

        $inputs = (new LoaderInputResolver())->resolve(
            $specification,
            new ResolverStubConfig(entity: 'product'),
            ['product' => 'never-read'],
        );

        static::assertSame('product', $inputs->get('entity'));
    }

    #[TestDox('a propertyReference key resolves the stored value its token names')]
    public function testPropertyReferenceResolvesStoredValue(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::referenceSpecification(),
            new ResolverStubConfig(property: 'entityId'),
            ['entityId' => 'product-alice'],
        );

        static::assertSame('product-alice', $inputs->get('property'));
    }

    #[TestDox('an object-referencing propertyReference key resolves the stored object instance')]
    public function testPropertyReferenceResolvesObject(): void
    {
        $product = new \stdClass();
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'object'),
        ]);

        $inputs = (new LoaderInputResolver())->resolve(
            $specification,
            new ResolverStubConfig(property: 'product'),
            ['product' => $product],
        );

        static::assertSame($product, $inputs->get('property'));
    }

    #[TestDox('a propertyReference key resolves an empty string, which is a value and not an absence')]
    public function testPropertyReferenceResolvesEmptyString(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::referenceSpecification(),
            new ResolverStubConfig(property: 'entityId'),
            ['entityId' => ''],
        );

        static::assertTrue($inputs->has('property'));
        static::assertSame('', $inputs->get('property'));
    }

    #[TestDox('a list-referencing propertyReference key resolves a stored list of strings')]
    public function testPropertyReferenceResolvesStringList(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::listReferenceSpecification(),
            new ResolverStubConfig(associationOverride: 'extraAssociations'),
            ['extraAssociations' => ['media', 'cover']],
        );

        static::assertSame(['media', 'cover'], $inputs->get('associationOverride'));
    }

    #[TestDox('a list-referencing propertyReference key resolves an empty list, which is a value and not an absence')]
    public function testPropertyReferenceResolvesEmptyList(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::listReferenceSpecification(),
            new ResolverStubConfig(associationOverride: 'extraAssociations'),
            ['extraAssociations' => []],
        );

        static::assertTrue($inputs->has('associationOverride'));
        static::assertSame([], $inputs->get('associationOverride'));
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('unresolvedReferenceProvider')]
    #[TestDox('a propertyReference key resolves to null: $_dataName')]
    public function testPropertyReferenceResolvesToNull(mixed $token, array $properties): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::referenceSpecification(),
            new ResolverStubConfig(property: $token),
            $properties,
        );

        static::assertFalse($inputs->has('property'));
        static::assertNull($inputs->get('property'));
    }

    #[TestDox('a stored value of the wrong type resolves to null rather than throwing')]
    public function testWrongTypedStoredValueResolvesToNull(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::referenceSpecification(),
            new ResolverStubConfig(property: 'entityId'),
            ['entityId' => 42],
        );

        static::assertNull($inputs->get('property'));
    }

    #[TestDox('a stored list carrying a non-string entry resolves to null rather than throwing')]
    public function testWrongTypedStoredListResolvesToNull(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::listReferenceSpecification(),
            new ResolverStubConfig(associationOverride: 'extraAssociations'),
            ['extraAssociations' => ['media', 42]],
        );

        static::assertNull($inputs->get('associationOverride'));
    }

    #[TestDox('a merging key folds its resolved list into the target, target entries first')]
    public function testMergeAppendsMergerAfterTarget(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::mergeSpecification(),
            new ResolverStubConfig(associations: ['configured'], associationOverride: 'extraAssociations'),
            ['extraAssociations' => ['stored']],
        );

        static::assertSame(['configured', 'stored'], $inputs->get('associations'));
    }

    #[TestDox('removes the merging key from resolved inputs after a merge')]
    public function testMergingKeyIsRemovedFromInputs(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::mergeSpecification(),
            new ResolverStubConfig(associations: ['configured'], associationOverride: 'extraAssociations'),
            ['extraAssociations' => ['stored']],
        );

        $this->expectExceptionObject(ContentSystemException::loaderInputNotDeclared('associationOverride', ['associations']));

        $inputs->get('associationOverride');
    }

    #[TestDox('a merge with both sides unresolved yields an empty list')]
    public function testMergeOfTwoUnresolvedSidesYieldsEmptyList(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::mergeSpecification(),
            new ResolverStubConfig(),
            [],
        );

        static::assertSame([], $inputs->get('associations'));
    }

    #[TestDox('a merge keeps an entry both sides carry rather than deduplicating it')]
    public function testMergeKeepsDuplicates(): void
    {
        $inputs = (new LoaderInputResolver())->resolve(
            self::mergeSpecification(),
            new ResolverStubConfig(associations: ['media'], associationOverride: 'extraAssociations'),
            ['extraAssociations' => ['media']],
        );

        static::assertSame(['media', 'media'], $inputs->get('associations'));
    }

    #[TestDox('throws when a declared key has no public property on the config class')]
    public function testThrowsWhenDeclaredKeyHasNoConfigProperty(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false),
        ]);

        $this->expectExceptionObject(ContentSystemException::loaderConfigKeyWithoutProperty(ResolverStubConfig::class, 'rootId'));

        (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(), []);
    }

    /**
     * @return iterable<string, array{mixed, array<array-key, mixed>}>
     */
    public static function unresolvedReferenceProvider(): iterable
    {
        // 'the token is null' and 'the token is not a string' both fail the `!is_string($token)` sub-predicate
        // of guard 1 and are merged into the one below. Each guard-1 row's properties carry a sentinel keyed
        // by the token's own value: if guard 1 (`!is_string($token) || $token === ''`) were deleted, dereference
        // would fall through to guard 2 and resolve the sentinel instead of null, so the row fails rather than
        // sliding through to guard 2's identical null result.
        yield 'the token is not a string' => [42, [42 => 'guard2-pass']];
        yield 'the token is an empty string' => ['', ['' => 'guard2-pass']];

        // 'the referenced key is absent' and 'the referenced value is null' both collapse through the `??` into
        // the same $value === null path in guard 2, so one row covers both.
        yield 'the referenced key is absent or its value is null' => ['entityId', []];
    }

    private static function referenceSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false),
        ]);
    }

    private static function listReferenceSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>'),
        ]);
    }

    private static function mergeSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }
}

/**
 * @internal
 *
 * Config properties are untyped on purpose: the resolver must handle a token of any stored shape.
 */
final readonly class ResolverStubConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public mixed $property = null,
        public mixed $entity = null,
        public mixed $label = null,
        public mixed $associations = null,
        public mixed $associationOverride = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
