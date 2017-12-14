<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroup;

use Shopware\Api\Customer\Struct\CustomerGroupSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
