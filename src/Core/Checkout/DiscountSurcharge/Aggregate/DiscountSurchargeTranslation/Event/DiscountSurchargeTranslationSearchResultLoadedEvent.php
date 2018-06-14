<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Struct\DiscountSurchargeTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class DiscountSurchargeTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge_translation.search.result.loaded';

    /**
     * @var DiscountSurchargeTranslationSearchResult
     */
    protected $result;

    public function __construct(DiscountSurchargeTranslationSearchResult $result)
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
