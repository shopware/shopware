<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\Customer;

use Shopware\Api\Customer\Struct\CustomerSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
