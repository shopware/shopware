<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Storefront\Page\Listing\ListingPageRequest;

class PageCriteriaCreatedEvent extends NestedEvent
{
    public const NAME = 'page.criteria.created.event';

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var \Shopware\Core\Checkout\CustomerContext
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
        return $this->context->getContext();
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
