<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\CustomerContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Storefront\Page\Listing\ListingPageRequest;

class PageCriteriaCreatedEvent extends NestedEvent
{
    public const NAME = 'page.criteria.created.event';

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var \Shopware\Checkout\CustomerContext
     */
    protected $context;

    /**
     * @var ListingPageRequest
     */
    protected $request;

    public function __construct(Criteria $criteria, CustomerContext $context, ListingPageRequest $request)
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
        return $this->context->getApplicationContext();
    }

    public function getStorefrontContext(): CustomerContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getRequest(): ListingPageRequest
    {
        return $this->request;
    }
}
