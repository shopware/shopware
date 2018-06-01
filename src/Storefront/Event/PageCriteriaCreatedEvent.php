<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Framework\Context;
use Shopware\Application\Context\Struct\StorefrontContext;
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
     * @var StorefrontContext
     */
    protected $context;

    /**
     * @var ListingPageRequest
     */
    protected $request;

    public function __construct(Criteria $criteria, StorefrontContext $context, ListingPageRequest $request)
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

    public function getStorefrontContext(): StorefrontContext
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
