<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRejection;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingRuntimeResolver;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingRuntimeResolver::class)]
class MappingRuntimeResolverTest extends TestCase
{
    private AbstractContentSystemMappingCandidateRegistry&MockObject $registry;

    private MappingCandidate $candidate;

    private MappingSourceResolutionContext $context;

    private RenderingCacheContext $cacheContext;

    private MappingRuntimeResolver $resolver;

    protected function setUp(): void
    {
        $this->candidate = new MappingCandidate('product.name', 'Name', '', 'basic', 'string');
        $this->registry = static::createMock(AbstractContentSystemMappingCandidateRegistry::class);
        $types = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $types->method('has')->willReturn(true);
        $types->method('get')->willReturn(ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', mappable: true, inlineMappable: true)
            ->build());
        $this->cacheContext = new RenderingCacheContext();
        $this->context = new MappingSourceResolutionContext(
            new StoredElement('element-1', 'Sw:Content:Text'),
            ['product' => 'root-data'],
            ['selectedName' => 'Shirt'],
            $this->cacheContext,
        );
        $this->resolver = new MappingRuntimeResolver($this->registry, new StoredMappingInspector(
            $types,
            $this->registry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        ));
    }

    #[DataProvider('mappingKinds')]
    public function testAdmittedResolutionPreservesTheProviderResultAndCacheDependencies(bool $inline): void
    {
        $expected = MappingSourceResolution::resolved('Shirt');
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveSource')->willReturnCallback(
            function (string $rootSource, MappingSourceReference $source, MappingSourceResolutionContext $context) use ($expected): MappingSourceResolution {
                static::assertSame('product', $rootSource);
                static::assertSame($this->candidate->source, $source);
                static::assertSame($this->context, $context);
                static::assertSame($this->cacheContext, $context->cacheContext);
                $context->cacheContext->addTags(['provider-data']);

                return $expected;
            },
        );

        static::assertSame($expected, $this->resolve($inline));
        static::assertSame(['provider-data'], $this->cacheContext->getTags());
    }

    #[DataProvider('mappingKinds')]
    public function testMissingDataRemainsEligibleForMediumSpecificFallback(bool $inline): void
    {
        $expected = MappingSourceResolution::noValue();
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveSource')->willReturn($expected);

        static::assertSame($expected, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testUnavailableCandidateIsRejectedBeforeProviderDispatch(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([]);
        $this->registry->expects($this->never())->method('resolveSource');

        static::assertSame(MappingRejection::Unavailable, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testIncompatibleValueIsRejected(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveSource')->willReturn(MappingSourceResolution::resolved(42));

        static::assertSame(MappingRejection::Incompatible, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testExplicitNullRemainsAResolvedValue(bool $inline): void
    {
        $expected = MappingSourceResolution::resolved(null);
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveSource')->willReturn($expected);

        static::assertSame($expected, $this->resolve($inline));
    }

    #[DataProvider('mappingKinds')]
    public function testProviderErrorsPropagate(bool $inline): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->once())->method('resolveSource')->willThrowException(new \TypeError('Provider failed'));

        $this->expectException(\TypeError::class);

        $this->resolve($inline);
    }

    public function testStoredCardinalityIsAdmittedBeforeProviderDispatch(): void
    {
        $this->registry->method('forRootSource')->willReturn([$this->candidate->path => $this->candidate]);
        $this->registry->expects($this->never())->method('resolveSource');

        static::assertSame(MappingRejection::Unavailable, $this->resolver->resolveProperty(
            'product',
            'text',
            ContextType::Collection,
            $this->candidate->source,
            $this->context,
        ));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function mappingKinds(): iterable
    {
        yield 'whole-field' => [false];
        yield 'inline' => [true];
    }

    private function resolve(bool $inline): MappingSourceResolution|MappingRejection
    {
        return $inline
            ? $this->resolver->resolveInline('product', $this->candidate->path, $this->context)
            : $this->resolver->resolveProperty('product', 'text', ContextType::Single, $this->candidate->source, $this->context);
    }
}
