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

use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\CircularCartCalculation;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Exception\LineItemNotFoundException;
use Shopware\Cart\LineItem\LineItemCollection;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Context\StorefrontContextServiceInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class StoreFrontCartService
{
    public const CART_NAME = 'shopware';

    public const CART_TOKEN_KEY = 'cart_token_' . self::CART_NAME;

    /**
     * @var CircularCartCalculation
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
     * @var OrderPersisterInterface
     */
    private $orderPersister;

    /**
     * @var Cart
     */
    private $cart;

    public function __construct(
        CircularCartCalculation $calculation,
        CartPersisterInterface $persister,
        StorefrontContextServiceInterface $contextService,
        SessionInterface $session,
        OrderPersisterInterface $orderPersister
    ) {
        $this->calculation = $calculation;
        $this->persister = $persister;
        $this->contextService = $contextService;
        $this->session = $session;
        $this->orderPersister = $orderPersister;
    }

    public function createNew(): CalculatedCart
    {
        $this->createNewCart();

        return $this->getCalculatedCart();
    }

    public function getCalculatedCart(): CalculatedCart
    {
        $container = $this->getCart();

        return $this->calculate($container);
    }

    public function add(LineItemInterface $item): void
    {
        $cart = $this->getCart();

        $cart->getLineItems()->add($item);

        $this->calculate($cart);
    }

    public function fill(LineItemCollection $lineItems): void
    {
        $cart = $this->getCart();

        $cart->getLineItems()->fill($lineItems->getElements());

        $this->calculate($cart);
    }

    /**
     * @throws LineItemNotFoundException
     */
    public function changeQuantity(string $identifier, int $quantity): void
    {
        $cart = $this->getCart();

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        $this->calculate($cart);
    }

    public function remove(string $identifier): void
    {
        $cart = $this->getCart();
        $cart->getLineItems()->remove($identifier);
        $this->calculate($cart);
    }

    public function order(): void
    {
        $this->orderPersister->persist(
            $this->getCalculatedCart(),
            $this->contextService->getStorefrontContext()
        );

        $this->createNewCart();
    }

    public function getCart(): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        if ($this->getCartToken() === null) {
            //first access for frontend session
            return $this->cart = $this->createNewCart();
        }

        try {
            //try to access existing cart, identified by session token
            return $this->cart = $this->persister->load(
                $this->getCartToken(),
                self::CART_NAME
            );
        } catch (\Exception $e) {
            //token not found, create new cart
            return $this->cart = $this->createNewCart();
        }
    }

    private function calculate(Cart $cart): CalculatedCart
    {
        $context = $this->contextService->getStorefrontContext();
        $calculated = $this->calculation->calculate($cart, $context);

        $this->save($calculated, $context);

        return $calculated;
    }

    private function save(CalculatedCart $calculatedCart, StorefrontContext $context): void
    {
        $this->persister->save($calculatedCart, $context);
        $this->session->set(self::CART_TOKEN_KEY, $calculatedCart->getToken());
        $this->cart = $calculatedCart->getCart();
    }

    private function createNewCart(): Cart
    {
        if ($token = $this->getCartToken()) {
            $this->persister->delete($token);
        }

        $this->cart = Cart::createNew(self::CART_NAME);
        $this->session->set(self::CART_TOKEN_KEY, $this->cart->getToken());

        return $this->cart;
    }

    private function getCartToken(): ? string
    {
        if ($this->session->has(self::CART_TOKEN_KEY)) {
            return $this->session->get(self::CART_TOKEN_KEY);
        }

        return null;
    }
}
