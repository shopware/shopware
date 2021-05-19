<?php declare(strict_types=1);

namespace ExampleCreateNew {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Symfony\Component\Routing\Annotation\Route;

    class NewCartController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function createNewCart(): void
        {
            // Load the token through the request or from other sources
            $token = '596a70b408014230a140fd5d94d3402b';
            $cart = $this->cartService->createNew($token);
        }
    }
} // code-example-end

namespace ExampleCurrentCart {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class GetCartController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function getCart(SalesChannelContext $salesChannelContext): void
        {
            // if not cart exists, a new one will be created
            $cart = $this->cartService->getCart(
                '596a70b408014230a140fd5d94d3402b',
                $salesChannelContext
            );
        }
    }
} // code-example-end

namespace ExampleAddToCart {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class AddToCartController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function addToCart(SalesChannelContext $salesChannelContext): void
        {
            // unique identifier to reference the line item, usually the source id, but can be random
            // and id of the referenced product
            $product = (new ProductLineItemFactory())->create('407f9c24dd414da485501085e3ead678', ['quantity' => 5]);

            $cart = $this->cartService->getCart('596a70b408014230a140fd5d94d3402b', $salesChannelContext);

            $this->cartService->add($cart, $product, $salesChannelContext);
        }
    }
} // code-example-end

namespace ExampleChangeQuantity {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class ChangeQuantityController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function changeLineItemQuantity(SalesChannelContext $salesChannelContext): void
        {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            $this->cartService->changeQuantity($cart, '407f9c24dd414da485501085e3ead678', 9, $salesChannelContext);
        }
    }
} // code-example-end

namespace ExampleRemoveItem {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class RemoveController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function removeLineItem(SalesChannelContext $salesChannelContext): void
        {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            $this->cartService->remove($cart, '407f9c24dd414da485501085e3ead678', $salesChannelContext);
        }
    }
} // code-example-end

namespace ExampleGetDeliveries {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class GetDeliveriesController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function getDeliveries(SalesChannelContext $salesChannelContext): void
        {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            $deliveries = $cart->getDeliveries();
        }
    }
} // code-example-end

namespace ExampleOrder {
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;
    use Symfony\Component\Routing\Annotation\Route;

    class PlaceOrderController
    {
        /**
         * @var CartService
         */
        private $cartService;

        /**
         * @Route("/", name="cart.test")
         */
        public function order(SalesChannelContext $salesChannelContext): void
        {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            $this->cartService->order($cart, $salesChannelContext, new RequestDataBag());
        }
    }
} // code-example-end

namespace DocsTest {
    use ExampleAddToCart\AddToCartController;
    use ExampleChangeQuantity\ChangeQuantityController;
    use ExampleCreateNew\NewCartController;
    use ExampleCurrentCart\GetCartController;
    use ExampleGetDeliveries\GetDeliveriesController;
    use ExampleOrder\PlaceOrderController;
    use ExampleRemoveItem\RemoveController;
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
    use Shopware\Core\Checkout\Cart\LineItem\LineItem;
    use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
    use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
    use Shopware\Core\Defaults;
    use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
    use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
    use Shopware\Core\Framework\Uuid\Uuid;
    use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
    use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
    use Shopware\Core\System\SalesChannel\SalesChannelContext;

    class DocsLineItemTest extends TestCase
    {
        use IntegrationTestBehaviour;

        private $salesChannelToken;

        /**
         * @before
         */
        public function resetCarts(): void
        {
            $cartService = $this->getContainer()->get(CartService::class);
            $refl = (new \ReflectionObject($cartService))->getProperty('cart');
            $refl->setAccessible(true);
            $refl->setValue($cartService, []);

            $this->salesChannelToken = Uuid::randomHex();
        }

        public function testLineItemIsInCorrectVersion(): void
        {
            static::assertSame(
                'd8f923b265f3e772c7570b3ee2b5aaed35654383',
                sha1_file(TEST_PROJECT_DIR . '/platform/src/Core/Checkout/Cart/LineItem/LineItem.php'),
                'The line item class changed apparently, ensure the docs are up to date'
            );
        }

        public function testExampleCreateNew(): void
        {
            $controller = new NewCartController();

            $this->setUpController($controller);
            $controller->createNewCart();

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleGetCart(): void
        {
            $controller = new GetCartController();

            $this->setUpController($controller);
            $controller->getCart($this->getSalesChannelContext());

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleAddToCart(): void
        {
            $controller = new AddToCartController();

            $this->ensureProduct('407f9c24dd414da485501085e3ead678');
            $this->setUpController($controller);
            $controller->addToCart($this->getSalesChannelContext());

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleChangeQuantity(): void
        {
            $this->ensureProductInCart();
            $controller = new ChangeQuantityController();

            $this->setUpController($controller);
            $controller->changeLineItemQuantity($this->getSalesChannelContext());

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleRemoveItem(): void
        {
            $this->ensureProductInCart();
            $controller = new RemoveController();

            $this->setUpController($controller);
            $controller->removeLineItem($this->getSalesChannelContext());

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleGetDeliveries(): void
        {
            $this->ensureProductInCart();
            $controller = new GetDeliveriesController();

            $this->setUpController($controller);
            $controller->getDeliveries($this->getSalesChannelContext());

            static::assertTrue(true); //indicate all went well
        }

        public function testExampleOrder(): void
        {
            $this->ensureProductInCart();
            $controller = new PlaceOrderController();
            $this->setUpController($controller);

            $this->expectException(CustomerNotLoggedInException::class);
            $controller->order($this->getSalesChannelContext());
        }

        private function setUpController(object $controller): void
        {
            $property = (new \ReflectionObject($controller))->getProperty('cartService');
            $property->setAccessible(true);
            $property->setValue($controller, $this->getContainer()->get(CartService::class));
        }

        private function ensureProduct(string $id): void
        {
            /** @var EntityRepositoryInterface $repo */
            $repo = $this->getContainer()->get('product.repository');
            $repo->upsert([[
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => Uuid::randomHex(), 'name' => 'test'],
                'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ]], $this->getSalesChannelContext()->getContext());
        }

        private function getSalesChannelContext(): SalesChannelContext
        {
            return $this->getContainer()
                ->get(SalesChannelContextService::class)
                ->get(new SalesChannelContextServiceParameters(Defaults::SALES_CHANNEL, $this->salesChannelToken));
        }

        private function ensureProductInCart(): void
        {
            $this->ensureProduct('407f9c24dd414da485501085e3ead678');
            $product = new LineItem('407f9c24dd414da485501085e3ead678', LineItem::PRODUCT_LINE_ITEM_TYPE, '407f9c24dd414da485501085e3ead678', 5);
            $product->setStackable(true);
            $product->setRemovable(true);
            $cartService = $this->getContainer()->get(CartService::class);
            $cartService->add(
                $cartService->getCart($this->getSalesChannelContext()->getToken(), $this->getSalesChannelContext()),
                $product,
                $this->getSalesChannelContext()
            );
        }
    }
}
