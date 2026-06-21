<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Resolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Resolution\AvailableContextResolver;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;

/**
 * @internal
 */
#[CoversClass(AvailableContextResolver::class)]
class AvailableContextResolverTest extends TestCase
{
    #[TestDox('returns the bound source root-ambient context for a top-level element, or empty when the source exposes none (header/footer)')]
    public function testTopLevelReceivesRootAmbient(): void
    {
        $root = new ContentElement('root-1', 'Sw:Block');

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame($rootContext, $this->resolver()->resolve('root-1', [$root], $rootContext));
        static::assertSame([], $this->resolver()->resolve('root-1', [$root], []));
    }

    #[TestDox('resolves ancestor provider context with the FQCN from the provider type spec for a nested element')]
    public function testNestedReceivesAncestorProvider(): void
    {
        $child = new ContentElement('child-1', 'Sw:Block');
        $root = new ContentElement(
            'root-1',
            'Sw:Provider',
            [],
            [],
            ['content' => new SlotContent([$child])],
            new ContextDefinitions(
                ['product' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
                [],
            ),
        );

        $available = $this->resolver()->resolve('child-1', [$root], []);

        static::assertCount(1, $available);
        static::assertSame('product', $available[0]->contextKey);
        static::assertSame(SalesChannelProductEntity::class, $available[0]->fqcn);
        static::assertSame('root-1', $available[0]->providerElementId);
        static::assertSame(DistributionStrategy::Broadcast, $available[0]->distribution);
    }

    #[TestDox('excludes a top-level sibling root-ambient context from a nested element')]
    public function testNestedDoesNotReceiveRootAmbient(): void
    {
        $child = new ContentElement('child-1', 'Sw:Block');
        $root = new ContentElement('root-1', 'Sw:Block', [], [], ['content' => new SlotContent([$child])]);

        $rootContext = $this->rootAmbientProductContext();

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('returns an empty set for an unknown element id')]
    public function testUnknownElementYieldsEmpty(): void
    {
        $root = new ContentElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('missing', [$root], []));
    }

    /**
     * @return list<ProvidedContext>
     */
    private function rootAmbientProductContext(): array
    {
        return [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];
    }

    private function resolver(): AvailableContextResolver
    {
        $providerSpec = new ContentSystemElementTypeSpecification(
            'Sw:Provider',
            'Provider',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            ['product' => new PropertySpecification(
                'product',
                new PropertyType(SalesChannelProductEntity::class, false, null, null),
                false,
                '',
                '',
                null,
            )],
            [],
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'Sw:Provider');
        $registry->method('get')->willReturn($providerSpec);

        return new AvailableContextResolver($registry);
    }
}
