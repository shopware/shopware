<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\Review;

use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;
use Symfony\Component\HttpFoundation\Request;

if (Feature::isActive('v6.7.0.0')) {
    #[Package('storefront')]
    class ProductReviewsLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
    {
        /**
         * @var EntitySearchResult<ProductReviewCollection>
         */
        protected EntitySearchResult $searchResult;

        /**
         * @var SalesChannelContext
         */
        protected $salesChannelContext;

        /**
         * @var Request
         */
        protected $request;

        /**
         * @param EntitySearchResult<ProductReviewCollection> $searchResult
         */
        public function __construct(
            EntitySearchResult $searchResult,
            SalesChannelContext $salesChannelContext,
            Request $request
        ) {
            $this->searchResult = $searchResult;
            $this->salesChannelContext = $salesChannelContext;
            $this->request = $request;
        }

        /**
         * @return EntitySearchResult<ProductReviewCollection>
         */
        public function getSearchResult(): EntitySearchResult
        {
            return $this->searchResult;
        }

        public function getSalesChannelContext(): SalesChannelContext
        {
            return $this->salesChannelContext;
        }

        public function getContext(): Context
        {
            return $this->salesChannelContext->getContext();
        }

        public function getRequest(): Request
        {
            return $this->request;
        }
    }
} else {
    #[Package('storefront')]
    class ProductReviewsLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
    {
        /**
         * @deprecated tag:v6.7.0 - Type will change to EntitySearchResult<ProductReviewCollection>
         *
         * @var StorefrontSearchResult<ProductReviewCollection>
         */
        protected $searchResult;

        /**
         * @var SalesChannelContext
         */
        protected $salesChannelContext;

        /**
         * @var Request
         */
        protected $request;

        /**
         * @param StorefrontSearchResult<ProductReviewCollection> $searchResult
         */
        public function __construct(
            StorefrontSearchResult $searchResult,
            SalesChannelContext $salesChannelContext,
            Request $request
        ) {
            $this->searchResult = $searchResult;
            $this->salesChannelContext = $salesChannelContext;
            $this->request = $request;
        }

        /**
         * @deprecated tag:v6.7.0 - Return type will change to EntitySearchResult<ProductReviewCollection>
         *
         * @return StorefrontSearchResult<ProductReviewCollection>
         */
        public function getSearchResult(): StorefrontSearchResult
        {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Return type will change to EntitySearchResult<ProductReviewCollection>');

            return $this->searchResult;
        }

        public function getSalesChannelContext(): SalesChannelContext
        {
            return $this->salesChannelContext;
        }

        public function getContext(): Context
        {
            return $this->salesChannelContext->getContext();
        }

        public function getRequest(): Request
        {
            return $this->request;
        }
    }
}
