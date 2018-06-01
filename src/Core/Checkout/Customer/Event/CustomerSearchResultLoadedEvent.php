<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\Struct\CustomerSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class CustomerSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer.search.result.loaded';

    /**
     * @var CustomerSearchResult
     */
    protected $result;

    public function __construct(CustomerSearchResult $result)
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
