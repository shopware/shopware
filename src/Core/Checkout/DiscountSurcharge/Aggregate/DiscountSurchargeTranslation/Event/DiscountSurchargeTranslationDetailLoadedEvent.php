<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\Collection\DiscountSurchargeTranslationDetailCollection;
use Shopware\Core\Checkout\DiscountSurcharge\Event\DiscountSurchargeBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class DiscountSurchargeTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var DiscountSurchargeTranslationDetailCollection
     */
    protected $discountSurchargeTranslations;

    public function __construct(DiscountSurchargeTranslationDetailCollection $discountSurchargeTranslations, Context $context)
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

    public function getDiscountSurchargeTranslations(): DiscountSurchargeTranslationDetailCollection
    {
        return $this->discountSurchargeTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->discountSurchargeTranslations->getDiscountSurcharges()->count() > 0) {
            $events[] = new DiscountSurchargeBasicLoadedEvent($this->discountSurchargeTranslations->getDiscountSurcharges(), $this->context);
        }
        if ($this->discountSurchargeTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->discountSurchargeTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
