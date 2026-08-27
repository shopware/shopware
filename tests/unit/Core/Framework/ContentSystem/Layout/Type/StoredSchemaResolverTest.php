<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredSchemaResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredSchemaResolver::class)]
class StoredSchemaResolverTest extends TestCase
{
    #[TestDox('publishes every declared primitive as a property entry, carrying a default only where the property declares one')]
    public function testResolveDeclaredPrimitivesToPropertyEntriesCarryingDefaultOnlyWhenDeclared(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->primitive('alt', 'string', required: true)
            ->primitive('maxImageWidth', 'integer', default: 1360)
            ->build();

        static::assertSame([
            'alt' => ['kind' => 'property', 'type' => 'string', 'required' => true],
            'maxImageWidth' => ['kind' => 'property', 'type' => 'integer', 'required' => false, 'default' => 1360],
        ], $this->resolver([])->resolve($type));
    }

    #[TestDox('omits a declared FQCN property from the storage schema, because nothing is stored under the reference key')]
    public function testResolveOmitsDeclaredReferencePropertyFromEntries(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->reference('media', MediaEntity::class, required: true)
            ->primitive('height', 'string', default: 'auto')
            ->build();

        static::assertSame([
            'height' => ['kind' => 'property', 'type' => 'string', 'required' => false, 'default' => 'auto'],
        ], $this->resolver([])->resolve($type));
    }

    #[TestDox('publishes the storage key of a synthesized default specification as a resolvedByStorage entry')]
    public function testResolveSynthesizedDefaultStorageKeyToResolvedByStorageEntry(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->reference('media', MediaEntity::class, required: true)
            ->build();

        // A synthesized default carries the type name as its id, which is what isDefault() derives from.
        $specification = new BindingSpecification(
            'Sw:Media:Image',
            'Sw:Media:Image',
            'Image',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            [],
            'core',
        );

        static::assertSame([
            'mediaId' => ['kind' => 'resolvedByStorage', 'type' => 'string', 'required' => true],
        ], $this->resolver(['core:Sw:Media:Image' => $specification], $this->entityLoaderKeys())->resolve($type));
    }

    #[TestDox('publishes the reference token of an authored specification as a config entry')]
    public function testResolveAuthoredSpecificationReferenceTokenToConfigEntry(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->reference('media', MediaEntity::class, required: true)
            ->build();

        // 'media-picker' !== 'Sw:Media:Image', so this specification is not the type's default, and its
        // storage-key config key is a plain config entry despite being named 'property'.
        $specification = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media Picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            [],
            'core',
        );

        static::assertSame([
            'mediaId' => ['kind' => 'config', 'type' => 'string', 'required' => true],
        ], $this->resolver(['core:media-picker' => $specification], $this->entityLoaderKeys())->resolve($type));
    }

    #[TestDox('publishes a config key referencedType rather than its own type, falls the token back to the key default, and emits no default at all: both keys declare type string, both declare a default, only associationOverride references a list')]
    public function testResolvePublishesReferencedTypeOfConfigKeyRatherThanItsOwnType(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Product:Listing')
            ->reference('products', ProductListingResult::class)
            ->build();

        $specification = new BindingSpecification(
            'listing-with-associations',
            'Sw:Product:Listing',
            'Product Listing',
            ['products' => new LoaderBinding('product_listing', [])],
            [],
            'core',
        );

        static::assertSame([
            'navigationId' => ['kind' => 'config', 'type' => 'string', 'required' => false],
            'associations' => ['kind' => 'config', 'type' => 'list<string>', 'required' => false],
        ], $this->resolver(['core:listing-with-associations' => $specification], $this->listingLoaderKeys())->resolve($type));
    }

    #[TestDox('prefers the property entry over a binding token naming the same stored key')]
    public function testResolvePrefersPropertyEntryOverBindingTokenOnSameStoredKey(): void
    {
        // The listing loader's 'property' key defaults to the token 'navigationId', which this type also
        // declares as a primitive property — the collision the property tier resolves.
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Product:Listing')
            ->reference('products', ProductListingResult::class)
            ->primitive('navigationId', 'string', required: true)
            ->build();

        $specification = new BindingSpecification(
            'listing-with-associations',
            'Sw:Product:Listing',
            'Product Listing',
            ['products' => new LoaderBinding('product_listing', [])],
            [],
            'core',
        );

        // The losing candidate is a config entry with required false; the winner is required true.
        static::assertSame([
            'navigationId' => ['kind' => 'property', 'type' => 'string', 'required' => true],
            'associations' => ['kind' => 'config', 'type' => 'list<string>', 'required' => false],
        ], $this->resolver(['core:listing-with-associations' => $specification], $this->listingLoaderKeys())->resolve($type));
    }

    #[TestDox('prefers the resolvedByStorage entry over a config entry naming the same stored key')]
    public function testResolvePrefersResolvedByStorageEntryOverConfigEntryOnSameStoredKey(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Product:Reviews')
            ->reference('reviews', ProductReviewResult::class)
            ->build();

        // The default specification is listed first, so a plain last-write-wins traversal would leave the
        // authored specification's config entry as the winner on 'productId'.
        $default = new BindingSpecification(
            'Sw:Product:Reviews',
            'Sw:Product:Reviews',
            'Reviews',
            ['reviews' => new LoaderBinding('product_review', ['property' => 'productId'])],
            [],
            'core',
        );

        $authored = new BindingSpecification(
            'reviews-with-associations',
            'Sw:Product:Reviews',
            'Reviews With Associations',
            ['reviews' => new LoaderBinding('product_review', ['associationOverride' => 'productId'])],
            [],
            'core',
        );

        // The losing candidate on 'productId' is a config entry of type list<string>; the winner is string.
        static::assertSame([
            'associations' => ['kind' => 'config', 'type' => 'list<string>', 'required' => false],
            'productId' => ['kind' => 'resolvedByStorage', 'type' => 'string', 'required' => false],
        ], $this->resolver(
            ['core:Sw:Product:Reviews' => $default, 'core:reviews-with-associations' => $authored],
            $this->reviewLoaderKeys(),
        )->resolve($type));
    }

    #[TestDox('skips a binding token naming a declared FQCN property, which claims storage where none exists')]
    public function testResolveSkipsBindingTokenNamingDeclaredReferenceProperty(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->reference('media', MediaEntity::class, required: true)
            ->primitive('height', 'string', default: 'auto')
            ->build();

        $specification = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media Picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'media'])],
            [],
            'core',
        );

        static::assertSame([
            'height' => ['kind' => 'property', 'type' => 'string', 'required' => false, 'default' => 'auto'],
        ], $this->resolver(['core:media-picker' => $specification], $this->entityLoaderKeys())->resolve($type));
    }

    #[TestDox('skips an integer-like binding token, which no stored key can be')]
    public function testResolveSkipsIntegerLikeToken(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Media:Image')
            ->reference('media', MediaEntity::class, required: true)
            ->primitive('height', 'string', default: 'auto')
            ->build();

        $specification = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media Picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => '42'])],
            [],
            'core',
        );

        static::assertSame([
            'height' => ['kind' => 'property', 'type' => 'string', 'required' => false, 'default' => 'auto'],
        ], $this->resolver(['core:media-picker' => $specification], $this->entityLoaderKeys())->resolve($type));
    }

    #[TestDox('resolves a type with neither primitives nor binding specifications to an empty map')]
    public function testResolveTypeWithoutPrimitivesAndSpecificationsToEmptyMap(): void
    {
        $type = ContentSystemElementTypeSpecificationBuilder::create('Sw:Layout:Spacer')->build();

        static::assertSame([], $this->resolver([])->resolve($type));
    }

    /**
     * The two config keys EntityLoader declares: the entityName key contributes nothing, the propertyReference
     * key is the resolvedBy shorthand's storage key.
     *
     * @return list<ConfigKeySpecification>
     */
    private function entityLoaderKeys(): array
    {
        return [
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
        ];
    }

    /**
     * The two propertyReference keys ProductListingDataLoader declares.
     *
     * @return list<ConfigKeySpecification>
     */
    private function listingLoaderKeys(): array
    {
        return [
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'navigationId'),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'associations', referencedType: 'list<string>'),
        ];
    }

    /**
     * The two propertyReference keys ProductReviewDataLoader declares.
     *
     * @return list<ConfigKeySpecification>
     */
    private function reviewLoaderKeys(): array
    {
        return [
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'productId'),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'associations', referencedType: 'list<string>'),
        ];
    }

    /**
     * byType() is final over all(), so stubbing all() gives the type filter for free.
     *
     * @param array<string, BindingSpecification> $specifications keyed by source-qualified id
     * @param list<ConfigKeySpecification> $configKeys the config contract every wired loader declares
     */
    private function resolver(array $specifications, array $configKeys = []): StoredSchemaResolver
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('configSpecification')->willReturn(new LoaderConfigSpecification($configKeys));

        // A real ServiceLocator, keyed by every loader name the given specifications actually reference, so
        // get() genuinely depends on the requested name instead of an unconditional stub masking a mis-wired one.
        $loaderNames = [];
        foreach ($specifications as $specification) {
            foreach ($specification->resolves() as $binding) {
                $loaderNames[$binding->loader] = static fn (): AbstractContentDataLoader => $loader;
            }
        }

        $dataLoaderProvider = new DataLoaderProvider(new ServiceLocator($loaderNames));

        // expects(once()) locks in the source class's own documented invariant (lines 76-79): resolve() reads
        // the registry in one traversal, never one read per kind, so two different snapshots can never disagree.
        $bindingSpecificationRegistry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingSpecificationRegistry->expects($this->once())->method('all')->willReturn($specifications);

        return new StoredSchemaResolver($bindingSpecificationRegistry, $dataLoaderProvider);
    }
}
