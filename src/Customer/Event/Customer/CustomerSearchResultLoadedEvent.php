<?php declare(strict_types=1);

namespace Shopware\Customer\Event\Customer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Struct\CustomerSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CustomerSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'customer.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
