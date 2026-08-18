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
    #[TestDox('a literal key takes its configured value')]
    public function testLiteralKeyTakesConfiguredValue(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('label', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'headline'),
        ]);

        $inputs = (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(label: 'title'), []);

        static::assertSame('title', $inputs->get('label'));
    }

    #[TestDox('a literal key falls back to its declared default when the config carries no value')]
    public function testLiteralKeyFallsBackToDeclaredDefault(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('label', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'headline'),
        ]);

        $inputs = (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(), []);

        static::assertSame('headline', $inputs->get('label'));
    }

    #[TestDox('a literal key without a declared default resolves to null when the config carries no value')]
    public function testLiteralKeyWithoutDefaultResolvesToNull(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('label', ConfigKeyKind::Literal, 'string', required: false),
        ]);

        $inputs = (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(), []);

        static::assertNull($inputs->get('label'));
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
     * @param array<string, mixed> $properties
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

    #[TestDox('throws when a declared key has no public property on the config class')]
    public function testThrowsWhenDeclaredKeyHasNoConfigProperty(): void
    {
        $specification = new LoaderConfigSpecification([
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false),
        ]);

        $this->expectExceptionObject(ContentSystemException::loaderConfigKeyWithoutProperty(ResolverStubConfig::class, 'rootId'));

        (new LoaderInputResolver())->resolve($specification, new ResolverStubConfig(), []);
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

    #[TestDox('a merging key is absent from the resolved inputs')]
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

    /**
     * @return iterable<string, array{mixed, array<string, mixed>}>
     */
    public static function unresolvedReferenceProvider(): iterable
    {
        yield 'the token is null' => [null, ['entityId' => 'product-alice']];
        yield 'the token is an empty string' => ['', ['entityId' => 'product-alice']];
        yield 'the token is not a string' => [42, ['entityId' => 'product-alice']];
        yield 'the referenced key is absent' => ['entityId', []];
        yield 'the referenced value is null' => ['entityId', ['entityId' => null]];
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
