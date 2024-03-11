<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Shopware\Storefront\Page\Page;

if (Feature::isActive('v6.7.0.0')) {
    #[Package('checkout')]
    class AccountOrderPage extends Page
    {
        /**
         * @var EntitySearchResult<OrderCollection>
         */
        protected EntitySearchResult $orders;

        /**
         * @var string|null
         */
        protected $deepLinkCode;

        /**
         * @return EntitySearchResult<OrderCollection>
         */
        public function getOrders(): EntitySearchResult
        {
            return $this->orders;
        }

        /**
         * @param EntitySearchResult<OrderCollection> $orders
         */
        public function setOrders(EntitySearchResult $orders): void
        {
            $this->orders = $orders;
        }

        public function getDeepLinkCode(): ?string
        {
            return $this->deepLinkCode;
        }

        public function setDeepLinkCode(?string $deepLinkCode): void
        {
            $this->deepLinkCode = $deepLinkCode;
        }
    }
} else {
    #[Package('checkout')]
    class AccountOrderPage extends Page
    {
        /**
         * @deprecated tag:v6.7.0 - Type will change to EntitySearchResult<OrderCollection>
         *
         * @var StorefrontSearchResult<OrderCollection>
         */
        protected $orders;

        /**
         * @var string|null
         */
        protected $deepLinkCode;

        /**
         * @deprecated tag:v6.7.0 - Return type will change to EntitySearchResult<OrderCollection>
         *
         * @return StorefrontSearchResult<OrderCollection>
         */
        public function getOrders(): StorefrontSearchResult
        {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Return type will change to EntitySearchResult<OrderCollection>');

            return $this->orders;
        }

        /**
         * @deprecated tag:v6.7.0 - Type will change to EntitySearchResult<OrderCollection>
         *
         * @param StorefrontSearchResult<OrderCollection> $orders
         */
        public function setOrders(StorefrontSearchResult $orders): void
        {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Type will change to EntitySearchResult<OrderCollection>');
            $this->orders = $orders;
        }

        public function getDeepLinkCode(): ?string
        {
            return $this->deepLinkCode;
        }

        public function setDeepLinkCode(?string $deepLinkCode): void
        {
            $this->deepLinkCode = $deepLinkCode;
        }
    }
}
