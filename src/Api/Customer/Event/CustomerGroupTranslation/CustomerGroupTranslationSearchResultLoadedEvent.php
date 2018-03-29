<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Event\CustomerGroupTranslation;

use Shopware\Api\Customer\Struct\CustomerGroupTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
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
