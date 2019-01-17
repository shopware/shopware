<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Shopware\Storefront\Pagelet\Search\SearchPageletRequest;

class PageCriteriaCreatedEvent extends NestedEvent
{
    public const NAME = 'page.criteria.created';

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ListingPageletRequest|SearchPageletRequest
     */
    protected $request;

    public function __construct(Criteria $criteria, CheckoutContext $context, ListingPageletRequest $request)
    {
        $this->criteria = $criteria;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    /**
     * @return ListingPageletRequest|SearchPageRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param ListingPageletRequest|SearchPageRequest $request
     */
    public function setRequest($request): void
    {
        $this->request = $request;
    }
}
