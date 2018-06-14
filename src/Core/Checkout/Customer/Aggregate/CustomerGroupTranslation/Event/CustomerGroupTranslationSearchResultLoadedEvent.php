<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class CustomerGroupTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group_translation.search.result.loaded';

    /**
     * @var CustomerGroupTranslationSearchResult
     */
    protected $result;

    public function __construct(CustomerGroupTranslationSearchResult $result)
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
