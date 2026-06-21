<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderTypeCapability;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(LayoutDiagnostics::class)]
class LayoutDiagnosticsTest extends TestCase
{
    #[TestDox('accepts an unsatisfied required reference in the well-formedness subset, emits no binding errors and exposes the analysed element in the resolutions map')]
    public function testWellFormednessSubsetIgnoresBinding(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $analysis = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, null);

        static::assertTrue($analysis->report->isWellFormed());
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertArrayHasKey('el-1', $analysis->resolutions);
    }

    #[TestDox('produces no binding error when a required reference is satisfied by root-ambient context')]
    public function testRootAmbientSatisfiesRequired(): void
    {
        $tree = [new ContentElement('root-1', 'Sw:Block')];

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, $rootContext)->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('emits an orphaned_provider warning without blocking when a provider has no consumer in scope')]
    public function testOrphanedProviderWarning(): void
    {
        $root = ContentElementBuilder::create('Sw:Block', 'root-1')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withSlot('content', [new ContentElement('child-1', 'Sw:Block')])
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$root], null)->report;

        static::assertTrue($report->isWellFormed());
        $warning = $this->single(array_filter($report->violations, static fn (Violation $v): bool => $v->code === ViolationCode::OrphanedProvider));
        static::assertSame('root-1', $warning->elementId);
    }

    #[TestDox('reports a duplicate element id across roots as an intrinsic error')]
    public function testDuplicateElementId(): void
    {
        $tree = [new ContentElement('dup', 'Sw:Block'), new ContentElement('dup', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::DuplicateElementId, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('reports an unregistered component as an intrinsic error')]
    public function testUnregisteredComponent(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Missing')];

        $report = $this->diagnostics([])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::UnregisteredComponent, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('produces an invalid_config intrinsic error for a data requirement naming an unknown entity')]
    public function testInvalidConfigForUnknownEntity(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()], loaderProvider: $this->loaderProvider($loader))
            ->analyze([$element], null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::InvalidConfig, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('produces an unresolved_required binding error for a required reference with no candidate')]
    public function testUnresolvedRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('product', SalesChannelProductEntity::class, required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('produces an ambiguous_required binding error carrying candidates when two complete loaders match')]
    public function testAmbiguousRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $map = new ContentSystemDataLoaderTypeMap([
            'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
            'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
        ]);

        $report = $this->diagnostics(
            ['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->reference('category', CategoryEntity::class, required: true)->build()],
            $map,
            $this->decodingSerializers(),
        )->analyze($tree, [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::AmbiguousRequired, $error->code);
        static::assertCount(2, $error->candidates);
    }

    #[TestDox('produces an unresolved_required binding error for a required primitive without a default')]
    public function testRequiredPrimitiveWithoutDefault(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->primitive('headline', 'string', required: true)->build()])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('produces a broken_required_chain binding error for a required accepts_context with no provider')]
    public function testBrokenRequiredChain(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withConsumer('product', ContextType::Single, required: true)
            ->build();

        $report = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()])->analyze([$element], [])->report;

        static::assertSame(ViolationCode::BrokenRequiredChain, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('propagates a non-client-defect exception during config resolution instead of converting it to invalid_config')]
    public function testInternalFaultPropagates(): void
    {
        $element = ContentElementBuilder::create('Sw:Block', 'el-1')
            ->withDataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class))
            ->build();

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $diagnostics = $this->diagnostics(['Sw:Block' => ContentSystemElementTypeSpecificationBuilder::create()->build()], loaderProvider: $this->loaderProvider($loader));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));

        $diagnostics->analyze([$element], null);
    }

    /**
     * @param array<string, ContentSystemElementTypeSpecification> $specs
     */
    private function diagnostics(
        array $specs,
        ?ContentSystemDataLoaderTypeMap $map = null,
        ?DataLoaderConfigSerializerProvider $serializers = null,
        ?DataLoaderProvider $loaderProvider = null,
    ): LayoutDiagnostics {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        $typeResolver = static::createStub(AbstractContentSystemDataLoaderTypeResolver::class);
        $typeResolver->method('resolve')->willReturn($map ?? new ContentSystemDataLoaderTypeMap([]));

        $elementResolver = new ElementResolver(
            $registry,
            $typeResolver,
            $serializers ?? static::createStub(DataLoaderConfigSerializerProvider::class),
        );

        return new LayoutDiagnostics(
            $registry,
            $elementResolver,
            new AvailableContextResolver($registry),
            new RootContextMapper($loaderProvider ?? static::createStub(DataLoaderProvider::class)),
        );
    }

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function loaderProvider(AbstractContentDataLoader $loader): DataLoaderProvider
    {
        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        return $provider;
    }

    private function decodingSerializers(): DataLoaderConfigSerializerProvider
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        return $serializers;
    }

    /**
     * @param list<Violation> $errors
     */
    private function onlyIntrinsicError(array $errors): Violation
    {
        return $this->single($errors);
    }

    /**
     * @param list<Violation> $errors
     */
    private function onlyBindingError(array $errors): Violation
    {
        return $this->single($errors);
    }

    /**
     * @param array<Violation> $violations
     */
    private function single(array $violations): Violation
    {
        static::assertCount(1, $violations);

        return array_values($violations)[0];
    }
}
