<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ProviderDeliveryKeyResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * The single owner of the child-facing delivery-key rule, shared by the serving path (WiringPlanner)
 * and the diagnostics context walk (AvailableContextResolver).
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProviderDeliveryKeyResolver::class)]
class ProviderDeliveryKeyResolverTest extends TestCase
{
    private ProviderDeliveryKeyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ProviderDeliveryKeyResolver();
    }

    #[DataProvider('collisionAxisProvider')]
    #[TestDox('rejects two child-facing key producers of one element that deliver under the same key')]
    public function testRejectsCollidingChildFacingKeys(ContextDefinitions $definitions, string $childKey, string $first, string $second): void
    {
        try {
            $this->resolver->resolve($definitions, 'element-id');
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, $childKey, $first, $second, 'element-id');
        }
    }

    /**
     * @return iterable<string, array{ContextDefinitions, string, string, string}>
     */
    public static function collisionAxisProvider(): iterable
    {
        // Two authored providers: distinct provider map keys, equal child-facing keys via their aliases.
        yield 'two authored providers sharing a consumer alias' => [
            StoredElementBuilder::create('section', 'element-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withProvider('category', BroadcastDistributionConfig::aliased('item'))
                ->build()->contextDefinitions,
            'item',
            'product',
            'category',
        ];

        // An authored provider against a redistribute consumer's derived provider: the derived provider's own
        // map key ('category') would not collide, only its child-facing key does.
        yield 'an authored provider and a derived provider' => [
            StoredElementBuilder::create('section', 'element-id')
                ->withProvider('product', BroadcastDistributionConfig::aliased('item'))
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item')
                ->build()->contextDefinitions,
            'item',
            'product',
            'category',
        ];

        // Two derived providers: the consumers write different properties (propertyAlias), so a check judged
        // on the derived provider map key would pass; both still deliver under 'item'.
        yield 'two derived providers sharing a consumer alias' => [
            StoredElementBuilder::create('section', 'element-id')
                ->withConsumer('product', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'productName')
                ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'item', propertyAlias: 'categoryName')
                ->build()->contextDefinitions,
            'item',
            'product',
            'category',
        ];
    }

    #[TestDox('returns every child-facing key of a clean element mapped to the producer delivering under it')]
    public function testReturnsTheChildFacingKeyMapOfACleanElement(): void
    {
        $element = StoredElementBuilder::create('section', 'element-id')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->withProvider('brand', BroadcastDistributionConfig::aliased('manufacturer'))
            ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'section')
            ->withConsumer('review', ContextType::Single)
            ->build();

        static::assertSame(
            ['product' => 'product', 'manufacturer' => 'brand', 'section' => 'category'],
            $this->resolver->resolve($element->contextDefinitions, $element->id)
        );
    }

    /**
     * The index order is load-bearing beyond the map: it decides which of two colliding producers is reported
     * as `first`, which the authored-provider-versus-derived-provider collision axis above pins.
     */
    #[TestDox('indexes authored providers before the providers derived from redistribute consumers')]
    public function testIndexesAuthoredProvidersBeforeDerivedOnes(): void
    {
        $element = StoredElementBuilder::create('section', 'element-id')
            ->withConsumer('category', ContextType::Single, redistribute: true, consumerAlias: 'section')
            ->withProvider('product', BroadcastDistributionConfig::simple())
            ->build();

        static::assertSame(
            ['product', 'section'],
            array_keys($this->resolver->resolve($element->contextDefinitions, $element->id))
        );
    }

    #[DataProvider('derivedChildKeyProvider')]
    #[TestDox('derives the child-facing key of a redistribute consumer from its consumer alias, falling back to its context key')]
    public function testDerivedChildKey(?string $consumerAlias, string $expected): void
    {
        $consumer = new ContextConsumer(ContextType::Single, false, true, $consumerAlias);

        static::assertSame($expected, $this->resolver->derivedChildKey($consumer, 'product'));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function derivedChildKeyProvider(): iterable
    {
        yield 'an alias names the child-facing key' => ['item', 'item'];
        yield 'without an alias the context key is the child-facing key' => [null, 'product'];
    }

    /**
     * ContextDefinitions carries no runtime key guard, and PHP coerces a numeric-string array key to int, so
     * an int-keyed definition map is constructible in memory (the decode path rejects it separately, in
     * StoredElementCodec). The keys are cast on the way into the string-typed collision exception, so this
     * path throws a ContentSystemException rather than a TypeError.
     */
    #[TestDox('reports a collision between numerically keyed producers with stringified payload values')]
    public function testReportsACollisionBetweenNumericallyKeyedProducers(): void
    {
        $element = StoredElementBuilder::create('section', 'element-id')
            ->withProvider('5', BroadcastDistributionConfig::aliased('item'))
            ->withConsumer('7', ContextType::Single, redistribute: true, consumerAlias: 'item')
            ->build();

        // Fixture guard: the keys really arrived as int. Without it this test goes vacuous the day a key
        // guard lands on ContextDefinitions and the numeric strings survive as strings.
        static::assertSame([5], array_keys($element->contextDefinitions->getAllProviders()));
        static::assertSame([7], array_keys($element->contextDefinitions->getAllConsumers()));

        try {
            $this->resolver->resolve($element->contextDefinitions, $element->id);
            static::fail('Expected a provider delivery collision.');
        } catch (ContentSystemException $exception) {
            $this->assertProviderDeliveryCollision($exception, 'item', '5', '7', 'element-id');
        }
    }

    /**
     * Pins the error code explicitly: expectExceptionObject() alone could not — Symfony's HttpException
     * leaves getCode() at 0, so an object comparison does not distinguish one ContentSystemException from
     * another.
     */
    private function assertProviderDeliveryCollision(
        ContentSystemException $exception,
        string $childKey,
        string $first,
        string $second,
        string $elementId
    ): void {
        static::assertSame(ContentSystemException::PROVIDER_DELIVERY_COLLISION, $exception->getErrorCode());
        static::assertSame($childKey, $exception->getParameter('childKey'));
        static::assertSame($first, $exception->getParameter('first'));
        static::assertSame($second, $exception->getParameter('second'));
        static::assertSame($elementId, $exception->getParameter('elementId'));
    }
}
