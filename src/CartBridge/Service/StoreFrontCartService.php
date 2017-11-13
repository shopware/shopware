<?php
declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\CartBridge\Service;

use Psr\Log\LoggerInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\CartCalculator;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Exception\LineItemNotFoundException;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\CartBridge\View\ViewCart;
use Shopware\CartBridge\View\ViewCartTransformer;
use Shopware\Context\Struct\ShopContext;
use Shopware\Storefront\Context\StorefrontContextServiceInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class StoreFrontCartService
{
    const CART_NAME = 'shopware';

    const CART_TOKEN_KEY = 'cart_token_' . self::CART_NAME;

    /**
     * @var CartCalculator
     */
    private $calculation;

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var StorefrontContextServiceInterface
     */
    private $contextService;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ViewCartTransformer
     */
    private $viewCartTransformer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderPersisterInterface
     */
    private $orderPersister;

    /**
     * @var \Shopware\Cart\Cart\Struct\CartContainer
     */
    private $cartContainer;

    /**
     * @var ViewCart
     */
    private $viewCart;

    public function __construct(
        CartCalculator $calculation,
        CartPersisterInterface $persister,
        StorefrontContextServiceInterface $contextService,
        SessionInterface $session,
        ViewCartTransformer $viewCartTransformer,
        LoggerInterface $logger,
        OrderPersisterInterface $orderPersister
    ) {
        $this->calculation = $calculation;
        $this->persister = $persister;
        $this->contextService = $contextService;
        $this->session = $session;
        $this->viewCartTransformer = $viewCartTransformer;
        $this->logger = $logger;
        $this->orderPersister = $orderPersister;
    }

    public function createNew(): ViewCart
    {
        $this->createNewCart();

        return $this->getCart();
    }

    public function getCart(): ViewCart
    {
        if ($this->viewCart) {
            return $this->viewCart;
        }

        $calculatedCart = $this->getCalculatedCart();

        $viewCart = $this->viewCartTransformer->transform(
            $calculatedCart,
            $this->contextService->getShopContext()
        );

        return $this->viewCart = $viewCart;
    }

    public function add(LineItemInterface $item): void
    {
        $this->viewCart = null;

        $cart = $this->getCartContainer();

        $cart->getLineItems()->add($item);

        $this->calculate($cart);
    }

    public function fill(LineItemCollection $lineItems): void
    {
        $this->viewCart = null;

        $cart = $this->getCartContainer();

        $cart->getLineItems()->fill($lineItems->getElements());

        $this->calculate($cart);
    }

    public function changeQuantity(string $identifier, int $quantity): void
    {
        $this->viewCart = null;

        $cart = $this->getCart()->getCalculatedCart()->getCartContainer();

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        $this->calculate($cart);
    }

    public function remove(string $identifier): void
    {
        $this->viewCart = null;

        $cartContainer = $this->getCartContainer();
        $cartContainer->getLineItems()->remove($identifier);
        $this->calculate($cartContainer);
    }

    public function order(): void
    {
        $this->orderPersister->persist(
            $this->getCart()->getCalculatedCart(),
            $this->contextService->getShopContext()
        );

        $this->createNewCart();
    }

    public function getCartContainer(): CartContainer
    {
        if ($this->cartContainer) {
            return $this->cartContainer;
        }

        if ($this->getCartToken() === null) {
            //first access for frontend session
            return $this->cartContainer = $this->createNewCart();
        }

        try {
            //try to access existing cartContainer, identified by session token
            return $this->cartContainer = $this->persister->load(
                $this->getCartToken(),
                self::CART_NAME
            );
        } catch (\Exception $e) {
            //token not found, create new cartContainer
            return $this->cartContainer = $this->createNewCart();
        }
    }

    private function getCalculatedCart(): CalculatedCart
    {
        $container = $this->getCartContainer();

        return $this->calculate($container);
    }

    private function calculate(CartContainer $cartContainer): CalculatedCart
    {
        $context = $this->contextService->getShopContext();
        $calculated = $this->calculation->calculate($cartContainer, $context);

        $this->save($calculated, $context);

        return $calculated;
    }

    private function save(CalculatedCart $calculatedCart, ShopContext $context): void
    {
        $this->persister->save($calculatedCart, $context);
        $this->session->set(self::CART_TOKEN_KEY, $calculatedCart->getToken());
        $this->cartContainer = $calculatedCart->getCartContainer();
    }

    private function createNewCart(): CartContainer
    {
        if ($token = $this->getCartToken()) {
            $this->persister->delete($token);
        }

        $this->cartContainer = CartContainer::createNew(self::CART_NAME);
        $this->session->set(self::CART_TOKEN_KEY, $this->cartContainer->getToken());
        $this->viewCart = null;

        return $this->cartContainer;
    }

    private function getCartToken(): ? string
    {
        if ($this->session->has(self::CART_TOKEN_KEY)) {
            return $this->session->get(self::CART_TOKEN_KEY);
        }

        return null;
    }
}
