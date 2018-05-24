<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerAddress\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CustomerAddressSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_address.search.result.loaded';

    /**
     * @var CustomerAddressSearchResult
     */
    protected $result;

    public function __construct(CustomerAddressSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
