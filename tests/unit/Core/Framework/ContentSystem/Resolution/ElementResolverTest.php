<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateVia;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(ElementResolver::class)]
class ElementResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, string|int|float|bool|null}>
     */
    public static function resolvesPrimitiveToStaticValueProvider(): iterable
    {
        yield 'an optional primitive with a default' => [false, 'Hello'];
        yield 'a required primitive without a default' => [true, null];
    }

    #[DataProvider('resolvesPrimitiveToStaticValueProvider')]
    #[TestDox('resolves $_dataName to a static value carrying type, default and required flag, never blocking')]
    public function testResolvesPrimitiveToStaticValue(bool $required, string|int|float|bool|null $default): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: $required, default: $default)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderTypeMap([]),
        );

        static::assertCount(1, $resolutions);
        static::assertSame('headline', $resolutions[0]->key);
        static::assertSame(PropertyKind::Primitive, $resolutions[0]->kind);
        static::assertSame('string', $resolutions[0]->type);
        static::assertSame($default, $resolutions[0]->default);
        static::assertSame($required, $resolutions[0]->required);
        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single matching ancestor provider, keeping loaders as alternatives')]
    public function testReferenceResolvesViaSingleParent(): void
    {
        $available = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: 'root-1',
            distribution: DistributionStrategy::Broadcast,
        )];

        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', $available),
            new ContentSystemDataLoaderTypeMap([
                'entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'], ['property'])],
            ]),
        );

        static::assertSame(PropertyKind::Reference, $resolutions[0]->kind);
        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateVia::Parent, $resolutions[0]->resolved->via);
        static::assertSame('root-1', $resolutions[0]->resolved->providerElementId);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('resolves a reference via the single complete loader when no provider is available')]
    public function testReferenceResolvesViaCompleteLoader(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderTypeMap([
                'category_fixed' => [new LoaderTypeCapability(CategoryEntity::class)],
            ]),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNotNull($resolutions[0]->resolved);
        static::assertSame(CandidateVia::Loader, $resolutions[0]->resolved->via);
        static::assertSame('category_fixed', $resolutions[0]->resolved->loaderSource);
        static::assertTrue($resolutions[0]->resolved->configComplete);
    }

    #[TestDox('leaves a reference unresolved with an incomplete candidate when its only loader has required config keys')]
    public function testReferenceWithIncompleteLoaderConfig(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderTypeMap([
                'entity' => [new LoaderTypeCapability(SalesChannelProductEntity::class, ['entity' => 'product'], ['property'])],
            ]),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(1, $resolutions[0]->candidates);
        static::assertSame(CandidateVia::Loader, $resolutions[0]->candidates[0]->via);
        static::assertFalse($resolutions[0]->candidates[0]->configComplete);
        static::assertSame(['entity' => 'product'], $resolutions[0]->candidates[0]->configTemplate);
    }

    #[TestDox('leaves a reference unresolved and lists every candidate when multiple complete loaders match')]
    public function testReferenceWithMultipleSourcesIsAmbiguous(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderTypeMap([
                'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
                'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
            ]),
            $this->serializersDecoding(succeeds: true),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertCount(2, $resolutions[0]->candidates);
    }

    #[TestDox('leaves a reference unresolved with no candidates when neither provider nor loader matches')]
    public function testReferenceWithNoSource(): void
    {
        $resolutions = $this->resolve(
            ContentSystemElementTypeSpecificationBuilder::create()->reference('product', ProductEntity::class, required: true)->build(),
            new ResolutionContext('el-1', []),
            new ContentSystemDataLoaderTypeMap([]),
        );

        static::assertNull($resolutions[0]->resolved);
        static::assertSame([], $resolutions[0]->candidates);
    }

    #[TestDox('yields no resolutions for an unregistered element type, leaving the defect to the diagnostics layer')]
    public function testUnregisteredTypeYieldsNoResolutions(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);

        $resolver = new ElementResolver(
            $registry,
            $this->typeResolver(new ContentSystemDataLoaderTypeMap([])),
            static::createStub(DataLoaderConfigSerializerProvider::class),
        );

        static::assertSame([], $resolver->resolve('Sw:Unknown', new ResolutionContext('el-1', [])));
    }

    /**
     * @return list<PropertyResolution>
     */
    private function resolve(
        ContentSystemElementTypeSpecification $spec,
        ResolutionContext $context,
        ContentSystemDataLoaderTypeMap $map,
        ?DataLoaderConfigSerializerProvider $serializers = null,
    ): array {
        $resolver = new ElementResolver(
            $this->registryReturning($spec),
            $this->typeResolver($map),
            $serializers ?? static::createStub(DataLoaderConfigSerializerProvider::class),
        );

        return $resolver->resolve('Sw:Block', $context);
    }

    private function registryReturning(ContentSystemElementTypeSpecification $spec): AbstractContentSystemElementTypeRegistry
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(true);
        $registry->method('get')->willReturn($spec);

        return $registry;
    }

    private function typeResolver(ContentSystemDataLoaderTypeMap $map): AbstractContentSystemDataLoaderTypeResolver
    {
        $resolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $resolver->method('resolve')->willReturn($map);

        return $resolver;
    }

    private function serializersDecoding(bool $succeeds): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);

        if ($succeeds) {
            $serializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

            return $serializers;
        }

        $serializers->method('decode')->willThrowException(ContentSystemException::configSerializerNotRegistered('x'));

        return $serializers;
    }
}
