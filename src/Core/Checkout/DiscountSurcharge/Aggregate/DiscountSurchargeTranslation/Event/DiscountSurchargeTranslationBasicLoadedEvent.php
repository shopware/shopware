<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class DiscountSurchargeTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge_translation.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationBasicCollection
     */
    protected $discountSurchargeTranslations;

    public function __construct(DiscountSurchargeTranslationBasicCollection $discountSurchargeTranslations, Context $context)
    {
        $this->context = $context;
        $this->discountSurchargeTranslations = $discountSurchargeTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDiscountSurchargeTranslations(): DiscountSurchargeTranslationBasicCollection
    {
        return $this->discountSurchargeTranslations;
    }
}
