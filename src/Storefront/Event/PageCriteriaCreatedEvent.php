<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

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
     * @var Request
     */
    protected $request;

    public function __construct(Criteria $criteria, StorefrontContext $context, Request $request)
    {
        $this->criteria = $criteria;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context->getShopContext();
    }

    public function getStorefrontContext(): StorefrontContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
