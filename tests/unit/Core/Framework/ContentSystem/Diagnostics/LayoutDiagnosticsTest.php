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
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ElementResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Schema\AbstractContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeMap;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[CoversClass(LayoutDiagnostics::class)]
class LayoutDiagnosticsTest extends TestCase
{
    #[TestDox('a duplicate element id across roots is reported as an intrinsic error')]
    public function testDuplicateElementId(): void
    {
        $tree = [new ContentElement('dup', 'Sw:Block'), new ContentElement('dup', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => $this->spec([])])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::DuplicateElementId, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('an unregistered component is reported as an intrinsic error')]
    public function testUnregisteredComponent(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Missing')];

        $report = $this->diagnostics([])->analyze($tree, null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::UnregisteredComponent, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('the well-formedness subset accepts an unsatisfied required reference and emits no binding errors')]
    public function testWellFormednessSubsetIgnoresBinding(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $analysis = $this->diagnostics(['Sw:Block' => $this->spec(['product' => $this->reference(SalesChannelProductEntity::class, true)])])
            ->analyze($tree, null);

        static::assertTrue($analysis->report->isWellFormed());
        static::assertSame([], $analysis->report->bindingErrors());
        static::assertArrayHasKey('el-1', $analysis->resolutions);
    }

    #[TestDox('a required reference with no candidate is an unresolved_required binding error')]
    public function testUnresolvedRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => $this->spec(['product' => $this->reference(SalesChannelProductEntity::class, true)])])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('a required reference with two complete loaders is an ambiguous_required binding error carrying candidates')]
    public function testAmbiguousRequired(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $map = new ContentSystemDataLoaderTypeMap([
            'category_a' => [new LoaderTypeCapability(CategoryEntity::class)],
            'category_b' => [new LoaderTypeCapability(CategoryEntity::class)],
        ]);

        $report = $this->diagnostics(
            ['Sw:Block' => $this->spec(['category' => $this->reference(CategoryEntity::class, true)])],
            $map,
            $this->decodingSerializers(),
        )->analyze($tree, [])->report;

        $error = $this->onlyBindingError($report->bindingErrors());
        static::assertSame(ViolationCode::AmbiguousRequired, $error->code);
        static::assertCount(2, $error->candidates);
    }

    #[TestDox('a required reference satisfied by root-ambient context produces no binding error')]
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

        $report = $this->diagnostics(['Sw:Block' => $this->spec(['product' => $this->reference(SalesChannelProductEntity::class, true)])])
            ->analyze($tree, $rootContext)->report;

        static::assertSame([], $report->bindingErrors());
    }

    #[TestDox('a required primitive without a default is an unresolved_required binding error')]
    public function testRequiredPrimitiveWithoutDefault(): void
    {
        $tree = [new ContentElement('el-1', 'Sw:Block')];

        $report = $this->diagnostics(['Sw:Block' => $this->spec(['headline' => $this->primitive('string', true, null)])])
            ->analyze($tree, [])->report;

        static::assertSame(ViolationCode::UnresolvedRequired, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('a required accepts_context with no provider is a broken_required_chain binding error')]
    public function testBrokenRequiredChain(): void
    {
        $element = new ContentElement(
            'el-1',
            'Sw:Block',
            [],
            [],
            [],
            new ContextDefinitions([], ['product' => new ContextConsumer(ContextType::Single, required: true)]),
        );

        $report = $this->diagnostics(['Sw:Block' => $this->spec([])])->analyze([$element], [])->report;

        static::assertSame(ViolationCode::BrokenRequiredChain, $this->onlyBindingError($report->bindingErrors())->code);
    }

    #[TestDox('a provider with no consumer in scope is an orphaned_provider warning that does not block')]
    public function testOrphanedProviderWarning(): void
    {
        $root = new ContentElement(
            'root-1',
            'Sw:Block',
            [],
            [],
            ['content' => new SlotContent([new ContentElement('child-1', 'Sw:Block')])],
            new ContextDefinitions(['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())], []),
        );

        $report = $this->diagnostics(['Sw:Block' => $this->spec([])])->analyze([$root], null)->report;

        static::assertTrue($report->isWellFormed());
        $warning = $this->single(array_filter($report->violations, static fn (Violation $v): bool => $v->code === ViolationCode::OrphanedProvider));
        static::assertSame('root-1', $warning->elementId);
    }

    #[TestDox('a data requirement naming an unknown entity is an invalid_config intrinsic error')]
    public function testInvalidConfigForUnknownEntity(): void
    {
        $element = new ContentElement(
            'el-1',
            'Sw:Block',
            ['product' => new DataRequirement('product', 'entity', $this->createMock(AbstractContentDataLoaderConfig::class))],
        );

        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $report = $this->diagnostics(['Sw:Block' => $this->spec([])], loaderProvider: $this->loaderProvider($loader))
            ->analyze([$element], null)->report;

        static::assertFalse($report->isWellFormed());
        static::assertSame(ViolationCode::InvalidConfig, $this->onlyIntrinsicError($report->intrinsicErrors())->code);
    }

    #[TestDox('a non-client-defect exception during config resolution propagates rather than becoming invalid_config')]
    public function testInternalFaultPropagates(): void
    {
        $element = new ContentElement(
            'el-1',
            'Sw:Block',
            ['product' => new DataRequirement('product', 'entity', $this->createMock(AbstractContentDataLoaderConfig::class))],
        );

        $loader = $this->createMock(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::layoutNotFound('x'));

        $diagnostics = $this->diagnostics(['Sw:Block' => $this->spec([])], loaderProvider: $this->loaderProvider($loader));

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

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
        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        $typeResolver = $this->createMock(AbstractContentSystemDataLoaderTypeResolver::class);
        $typeResolver->method('resolve')->willReturn($map ?? new ContentSystemDataLoaderTypeMap([]));

        $elementResolver = new ElementResolver(
            $registry,
            $typeResolver,
            $serializers ?? $this->createMock(DataLoaderConfigSerializerProvider::class),
        );

        return new LayoutDiagnostics(
            $registry,
            $elementResolver,
            new AvailableContextResolver($registry),
            new RootContextMapper($loaderProvider ?? $this->createMock(DataLoaderProvider::class)),
        );
    }

    /**
     * @param AbstractContentDataLoader<Struct> $loader
     */
    private function loaderProvider(AbstractContentDataLoader $loader): DataLoaderProvider
    {
        $provider = $this->createMock(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        return $provider;
    }

    private function decodingSerializers(): DataLoaderConfigSerializerProvider
    {
        $serializers = $this->createMock(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($this->createMock(AbstractContentDataLoaderConfig::class));

        return $serializers;
    }

    /**
     * @param array<string, PropertySpecification> $properties
     */
    private function spec(array $properties): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'Sw:Block',
            'Block',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            $properties,
            [],
        );
    }

    private function reference(string $fqcn, bool $required): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($fqcn, false, null, null), $required, '', '', null);
    }

    private function primitive(string $type, bool $required, string|int|float|bool|null $default): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, $default), $required, '', '', null);
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
