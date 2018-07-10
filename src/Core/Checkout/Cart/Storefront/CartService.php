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

namespace Shopware\Core\Checkout\Cart\Storefront;

use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Order\OrderDefinition;

class CartService
{
    public const CART_NAME = 'shopware';

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var CartPersisterInterface
     */
    private $persister;

    /**
     * @var OrderPersisterInterface
     */
    private $orderPersister;

    /**
     * @var Cart|null
     */
    private $cart;

    /**
     * @var Enrichment
     */
    private $enrichment;

    public function __construct(
        Enrichment $enrichment,
        Processor $processor,
        CartPersisterInterface $persister,
        OrderPersisterInterface $orderPersister
    ) {
        $this->processor = $processor;
        $this->persister = $persister;
        $this->orderPersister = $orderPersister;
        $this->enrichment = $enrichment;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function createNew(CheckoutContext $context): Cart
    {
        $this->createNewCart($context);

        return $this->getCart($context);
    }

    public function getCart(CheckoutContext $context): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $cart = $this->loadOrCreateCart($context);

        return $this->calculate($cart, $context);
    }

    public function add(LineItem $item, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);

        $cart->add($item);

        return $this->calculate($cart, $context);
    }

    public function fill(LineItemCollection $lineItems, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);

        $cart->getLineItems()->fill($lineItems->getElements());

        return $this->calculate($cart, $context);
    }

    public function changeQuantity(string $identifier, int $quantity, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);

        if (!$lineItem = $cart->getLineItems()->get($identifier)) {
            throw new LineItemNotFoundException($identifier);
        }

        $lineItem->setQuantity($quantity);

        return $this->calculate($cart, $context);
    }

    public function remove(string $identifier, CheckoutContext $context): Cart
    {
        $cart = $this->loadOrCreateCart($context);

        $cart->getLineItems()->remove($identifier);

        return $this->calculate($cart, $context);
    }

    public function order(CheckoutContext $context): string
    {
        $events = $this->orderPersister->persist(
            $this->getCart($context),
            $context
        );

        $this->createNewCart($context);

        $event = $events->getEventByDefinition(OrderDefinition::class);
        $ids = $event->getIds();

        return array_shift($ids);
    }

    private function loadOrCreateCart(CheckoutContext $context): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        try {
            //try to access existing cart, identified by session token
            return $this->cart = $this->persister->load(
                $context->getToken(),
                self::CART_NAME,
                $context
            );
        } catch (\Exception $e) {
            //token not found, create new cart
            return $this->cart = $this->createNewCart($context);
        }
    }

    private function calculate(Cart $cart, CheckoutContext $context): Cart
    {
        $cart = $this->enrichment->enrich($cart, $context);

        $cart = $this->processor->process($cart, $context);

        $this->save($cart, $context);

        $this->cart = $cart;

        return $cart;
    }

    private function save(Cart $cart, CheckoutContext $context): void
    {
        $this->persister->save($cart, $context);
        $this->cart = $cart;
    }

    private function createNewCart(CheckoutContext $context): Cart
    {
        $this->persister->delete($context->getToken(), self::CART_NAME, $context);
        $this->cart = new Cart(self::CART_NAME, $context->getToken());

        return $this->cart;
    }
}
