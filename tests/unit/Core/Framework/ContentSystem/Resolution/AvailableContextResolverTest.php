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
    #[TestDox('a top-level element receives the bound source root-ambient context')]
    public function testTopLevelReceivesRootAmbient(): void
    {
        $root = new ContentElement('root-1', 'Sw:Block');

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        $available = $this->resolver()->resolve('root-1', [$root], $rootContext);

        static::assertSame($rootContext, $available);
    }

    #[TestDox('a top-level element receives nothing when the bound source exposes no root context (header/footer)')]
    public function testTopLevelWithEmptyRootContext(): void
    {
        $root = new ContentElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('root-1', [$root], []));
    }

    #[TestDox('a nested element receives its ancestor providers with the FQCN resolved from the provider type spec')]
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

    #[TestDox('a nested element does not inherit the root-ambient context of a top-level sibling')]
    public function testNestedDoesNotReceiveRootAmbient(): void
    {
        $child = new ContentElement('child-1', 'Sw:Block');
        $root = new ContentElement('root-1', 'Sw:Block', [], [], ['content' => new SlotContent([$child])]);

        $rootContext = [new ProvidedContext(
            contextKey: 'product',
            fqcn: SalesChannelProductEntity::class,
            contextType: ContextType::Single,
            providerElementId: VirtualRootWrapper::VIRTUAL_ROOT_ID,
            distribution: DistributionStrategy::Broadcast,
        )];

        static::assertSame([], $this->resolver()->resolve('child-1', [$root], $rootContext));
    }

    #[TestDox('an unknown element id resolves to an empty available set')]
    public function testUnknownElementYieldsEmpty(): void
    {
        $root = new ContentElement('root-1', 'Sw:Block');

        static::assertSame([], $this->resolver()->resolve('missing', [$root], []));
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

        $registry = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => $name === 'Sw:Provider');
        $registry->method('get')->willReturn($providerSpec);

        return new AvailableContextResolver($registry);
    }
}
