<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Event\CustomerGroup;

use Shopware\Api\Entity\Search\IdSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CustomerGroupIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'customer_group.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
