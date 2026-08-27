<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\PageContextConsumerWiring;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyKind;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PageContextConsumerWiring::class)]
class PageContextConsumerWiringTest extends TestCase
{
    private const PRODUCT_FQCN = 'Shopware\\Core\\Content\\Product\\SalesChannel\\SalesChannelProductEntity';

    #[TestDox('seeds an unresolved reference from the matching provided context')]
    public function testSeedsUnresolvedReference(): void
    {
        $price = new StoredElement('p1', 'Sw:Product:PriceDisplay');

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$price]),
            ['p1' => [$this->reference('product', self::PRODUCT_FQCN, true)]],
            [$this->productContext()],
        );

        $consumer = $this->consumers($wired->roots[0])['product'] ?? null;
        static::assertInstanceOf(ContextConsumer::class, $consumer);
        static::assertSame(ContextType::Single, $consumer->type);
        static::assertTrue($consumer->required);
        static::assertFalse($consumer->redistribute);
    }

    #[TestDox('relays redistribute up every ancestor to the root')]
    public function testRelaysUpAncestors(): void
    {
        $price = new StoredElement('price', 'Sw:Product:PriceDisplay');
        $inner = new StoredElement('inner', 'Sw:Grid:Container', [], [], ['content' => [$price]]);
        $outer = new StoredElement('outer', 'Sw:Grid:Container', [], [], ['content' => [$inner]]);

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$outer]),
            ['price' => [$this->reference('product', self::PRODUCT_FQCN)]],
            [$this->productContext()],
        );

        $wiredOuter = $wired->roots[0];
        $wiredInner = $wiredOuter->slots['content'][0];
        $wiredPrice = $wiredInner->slots['content'][0];

        static::assertFalse($this->consumers($wiredPrice)['product']->redistribute);

        foreach ([$wiredInner, $wiredOuter] as $ancestor) {
            $relay = $this->consumers($ancestor)['product'] ?? null;
            static::assertInstanceOf(ContextConsumer::class, $relay);
            static::assertTrue($relay->redistribute);
            static::assertFalse($relay->required);
        }
    }

    #[TestDox('wires a parent-resolved reference (root-level) from its resolved binding')]
    public function testWiresParentResolvedReference(): void
    {
        $price = new StoredElement('p1', 'Sw:Product:PriceDisplay');
        $resolved = new ResolutionCandidate(
            CandidateOrigin::Parent,
            'product',
            '__page_context_root__',
            null,
            DistributionStrategy::Broadcast,
            ContextType::Single,
        );

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$price]),
            ['p1' => [$this->reference('product', self::PRODUCT_FQCN, false, $resolved)]],
            [],
        );

        static::assertArrayHasKey('product', $this->consumers($wired->roots[0]));
    }

    #[TestDox('ignores a same-type reference whose name does not match the provided context key')]
    public function testIgnoresNameMismatch(): void
    {
        $element = new StoredElement('e1', 'Sw:Test');

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['e1' => [$this->reference('crossSellProduct', self::PRODUCT_FQCN)]],
            [$this->productContext()],
        );

        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('leaves a reference filled by a loader or stored wiring untouched')]
    public function testIgnoresSelfFilledReference(): void
    {
        $element = new StoredElement('e1', 'Sw:Test');
        $stored = new ResolutionCandidate(CandidateOrigin::Stored);

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$element]),
            ['e1' => [$this->reference('product', self::PRODUCT_FQCN, false, $stored)]],
            [$this->productContext()],
        );

        static::assertSame([], $this->consumers($wired->roots[0]));
    }

    #[TestDox('never overrides an authored consumer')]
    public function testNeverOverridesAuthoredConsumer(): void
    {
        $authored = new ContextConsumer(ContextType::Single, true, false, null, 'item');
        $price = new StoredElement(
            'p1',
            'Sw:Product:PriceDisplay',
            [],
            [],
            [],
            new ContextDefinitions([], ['product' => $authored]),
        );

        $wired = (new PageContextConsumerWiring())->apply(
            new StoredTree([$price]),
            ['p1' => [$this->reference('product', self::PRODUCT_FQCN)]],
            [$this->productContext()],
        );

        static::assertSame($authored, $this->consumers($wired->roots[0])['product']);
    }

    /**
     * @return array<string, ContextConsumer>
     */
    private function consumers(StoredElement $element): array
    {
        return $element->contextDefinitions->getAllConsumers();
    }

    private function reference(string $key, string $fqcn, bool $required = false, ?ResolutionCandidate $resolved = null): PropertyResolution
    {
        return new PropertyResolution($key, PropertyKind::Reference, $required, null, null, $fqcn, $resolved);
    }

    private function productContext(): ProvidedContext
    {
        return new ProvidedContext('product', self::PRODUCT_FQCN, ContextType::Single, '__page_context_root__', DistributionStrategy::Broadcast);
    }
}
