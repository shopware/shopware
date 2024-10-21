<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Traits\Promotion;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('checkout')]
trait PromotionIntegrationTestBehaviour
{
    private SalesChannelContext $context;

    /**
     * Gets a faked sales channel context
     * for the unit tests.
     */
    public function getContext(): SalesChannelContext
    {
        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        return $this->context;
    }

    /**
     * Adds the provided product to the cart.
     *
     * @throws CartException
     */
    public function addProduct(string $productId, int $quantity, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $factory = $this->getContainer()->get(ProductLineItemFactory::class);
        $product = $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $product, $context);
    }

    /**
     * Adds the provided code to the current cart.
     */
    public function addPromotionCode(string $code, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $itemBuilder = new PromotionItemBuilder();

        // ??? currencyPrecision is unused
        $lineItem = $itemBuilder->buildPlaceholderItem($code);

        $cart = $cartService->add($cart, $lineItem, $context);

        return $cart;
    }

    /**
     * Removes the provided code to the current cart.
     */
    public function removePromotionCode(string $code, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $promotions = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);

        foreach ($promotions->getElements() as $promotion) {
            if ($promotion->getReferencedId() === $code) {
                return $cartService->remove($cart, $promotion->getId(), $context);
            }
        }

        return $cart;
    }

    /**
     * Gets all promotion codes that have been added
     * to the current session.
     *
     * @return array<mixed>
     */
    public function getSessionCodes(): array
    {
        $mockFileSessionStorage = $this->getContainer()->get('session.storage.mock_file');
        static::assertInstanceOf(SessionStorageInterface::class, $mockFileSessionStorage);
        $session = new Session($mockFileSessionStorage);

        if (!$session->has(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES)) {
            return [];
        }

        return $session->get(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES);
    }
}
