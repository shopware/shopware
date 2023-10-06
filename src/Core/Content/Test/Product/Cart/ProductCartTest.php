<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\TestShortHands;

/**
 * @internal
 * This test is used as "good" reference integration tests inside our guidelines.
 */
class ProductCartTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    /**
     * @dataProvider priceInCartProvider
     *
     * @param array<string, mixed> $contextOptions
     */
    public function testPriceInCart(ProductBuilder $builder, float $expected, array $contextOptions = []): void
    {
        // the product builder has a helper function to write the product values to the database, including all dependencies (rules, currencies, properties, etc)
        $builder->write($this->getContainer());

        $context = $this->getContext(Uuid::randomHex(), $contextOptions);

        // `addProductToCart` is a small generic helper method to create a product and add it into the cart within as one liner
        $cart = $this->addProductToCart($builder->id, $context);

        static::assertTrue($cart->has($builder->id));

        $item = $cart->get($builder->id);

        static::assertInstanceOf(LineItem::class, $item);

        static::assertEquals($builder->id, $item->getId());

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice());
        static::assertEquals($expected, $item->getPrice()->getTotalPrice());
    }

    public static function priceInCartProvider(): \Generator
    {
        $ids = new IdsCollection();

        // Important hint: You are not allowed to write values within this function, they are not detected by our database
        // transaction behaviour. So they will not be deleted, and you remain artifacts inside the database

        yield 'Test simple price' => [
            (new ProductBuilder($ids, 'example-1'))->price(100)->visibility(),
            100,
        ];

        yield 'Test another price' => [
            (new ProductBuilder($ids, 'example-1'))->price(200)->visibility(),
            200,
        ];

        yield 'Test different currency' => [
            (new ProductBuilder($ids, 'example-1'))
                ->visibility()
                ->price(30)
                ->price(200, 100, 'dollar'),
            200,
            ['currencyId' => $ids->get('dollar')],
        ];
    }
}
