<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Event;

use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\Struct\CustomerGroupTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
