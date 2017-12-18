<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerAddress;

use Shopware\Api\Customer\Struct\CustomerAddressSearchResult;
use Shopware\Context\Struct\TranslationContext;
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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
