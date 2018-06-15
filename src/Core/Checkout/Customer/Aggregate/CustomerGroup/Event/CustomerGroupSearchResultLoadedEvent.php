<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Struct\CustomerGroupSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CustomerGroupSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.search.result.loaded';

    /**
     * @var CustomerGroupSearchResult
     */
    protected $result;

    public function __construct(CustomerGroupSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
