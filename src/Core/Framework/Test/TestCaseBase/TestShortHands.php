<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

trait TestShortHands
{
    use KernelTestBehaviour;

    private function createContext(array $options = []): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);
    }

    private function addProductToCart(string $id, SalesChannelContext $context): Cart
    {
        $product = $this->getContainer()->get(ProductLineItemFactory::class)
            ->create($id);

        $cart = $this->getContainer()->get(CartService::class)
            ->getCart($context->getToken(), $context);

        return $this->getContainer()->get(CartService::class)
            ->add($cart, $product, $context);
    }
}
