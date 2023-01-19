<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\QuantityInformation;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Content\Product\Cart\ProductLineItemValidator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 * @covers \Shopware\Core\Content\Product\Cart\ProductLineItemValidator
 */
class ProductLineItemValidatorTest extends TestCase
{
    public function testValidateOnDuplicateProductsAtMaxPurchase(): void
    {
        $cart = new Cart(Uuid::randomHex(), Uuid::randomHex());
        $builder = new ProductLineItemFactory();
        $cart->add(
            $builder
            ->create('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create('product-2')
            ->setReferencedId('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(1)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $this->createMock(SalesChannelContext::class));

        static::assertCount(1, $cart->getErrors());
    }

    public function testValidateOnDuplicateProductsWithSafeQuantity(): void
    {
        $cart = new Cart(Uuid::randomHex(), Uuid::randomHex());
        $builder = new ProductLineItemFactory();
        $cart->add(
            $builder
            ->create('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );
        $cart->add(
            $builder
            ->create('product-2')
            ->setReferencedId('product-1')
            ->setQuantityInformation(
                (new QuantityInformation())
                ->setMinPurchase(1)
                ->setMaxPurchase(3)
                ->setPurchaseSteps(1)
            )
        );

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $cart->getErrors());
    }

    public function testValidateOnDuplicateProductsWithoutQuantityInformation(): void
    {
        $cart = new Cart(Uuid::randomHex(), Uuid::randomHex());
        $builder = new ProductLineItemFactory();
        $cart->add($builder->create('product-1'));
        $cart->add($builder->create('product-2')->setReferencedId('product-1'));

        static::assertCount(0, $cart->getErrors());

        $validator = new ProductLineItemValidator();
        $validator->validate($cart, $cart->getErrors(), $this->createMock(SalesChannelContext::class));

        static::assertCount(0, $cart->getErrors());
    }
}
