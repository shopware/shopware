<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ListingPageletCriteriaCreatedEvent extends NestedEvent
{
    public const NAME = 'listing.pagelet.criteria.created';

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var \Shopware\Core\System\SalesChannel\SalesChannelContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(Criteria $criteria, SalesChannelContext $context, InternalRequest $request)
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

    public function getCheckoutContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }

    /**
     * @param InternalRequest $request
     */
    public function setRequest($request): void
    {
        $this->request = $request;
    }
}
