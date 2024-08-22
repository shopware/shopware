<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

if (Feature::isActive('v6.7.0.0')) {
    #[Package('checkout')]
    class BeforeLineItemQuantityChangedEvent implements ShopwareSalesChannelEvent, CartEvent
    {
        public function __construct(
            protected readonly LineItem $lineItem,
            protected readonly Cart $cart,
            protected readonly SalesChannelContext $salesChannelContext,
            protected readonly int $beforeUpdateQuantity
        ) {
        }

        public function getLineItem(): LineItem
        {
            return $this->lineItem;
        }

        public function getCart(): Cart
        {
            return $this->cart;
        }

        public function getContext(): Context
        {
            return $this->salesChannelContext->getContext();
        }

        public function getSalesChannelContext(): SalesChannelContext
        {
            return $this->salesChannelContext;
        }

        public function getBeforeUpdateQuantity(): int
        {
            return $this->beforeUpdateQuantity;
        }
    }
} else {
    #[Package('checkout')]
    class BeforeLineItemQuantityChangedEvent implements ShopwareSalesChannelEvent, CartEvent
    {
        protected int $beforeUpdateQuantity;

        /**
         * @var LineItem
         */
        protected $lineItem;

        /**
         * @var Cart
         */
        protected $cart;

        /**
         * @var SalesChannelContext
         */
        protected $salesChannelContext;

        /**
         * @deprecated tag:v6.7.0 - $beforeUpdateQuantity property will be added and all properties will be readonly
         */
        public function __construct(
            LineItem $lineItem,
            Cart $cart,
            SalesChannelContext $salesChannelContext
        ) {
            $this->lineItem = $lineItem;
            $this->cart = $cart;
            $this->salesChannelContext = $salesChannelContext;
        }

        public function getLineItem(): LineItem
        {
            return $this->lineItem;
        }

        public function getCart(): Cart
        {
            return $this->cart;
        }

        public function getContext(): Context
        {
            return $this->salesChannelContext->getContext();
        }

        public function getSalesChannelContext(): SalesChannelContext
        {
            return $this->salesChannelContext;
        }

        public function getBeforeUpdateQuantity(): int
        {
            return $this->beforeUpdateQuantity;
        }

        /**
         * @deprecated tag:v6.7.0 - $beforeUpdateQuantity property will be set in constructor
         */
        public function setBeforeUpdateQuantity(int $beforeUpdateQuantity): void
        {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', '$beforeUpdateQuantity property will be set in constructor');
            $this->beforeUpdateQuantity = $beforeUpdateQuantity;
        }
    }
}
