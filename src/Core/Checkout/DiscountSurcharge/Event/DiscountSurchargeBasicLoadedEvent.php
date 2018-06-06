<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class DiscountSurchargeBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var DiscountSurchargeBasicCollection
     */
    protected $discountSurcharges;

    public function __construct(DiscountSurchargeBasicCollection $discountSurcharges, Context $context)
    {
        $this->context = $context;
        $this->discountSurcharges = $discountSurcharges;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDiscountSurcharges(): DiscountSurchargeBasicCollection
    {
        return $this->discountSurcharges;
    }
}
