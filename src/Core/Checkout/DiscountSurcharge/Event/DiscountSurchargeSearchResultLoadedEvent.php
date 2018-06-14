<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class DiscountSurchargeSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge.search.result.loaded';

    /**
     * @var DiscountSurchargeSearchResult
     */
    protected $result;

    public function __construct(DiscountSurchargeSearchResult $result)
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
