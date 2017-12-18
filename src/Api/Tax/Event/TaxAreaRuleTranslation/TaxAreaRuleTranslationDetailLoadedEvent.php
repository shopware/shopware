<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Api\Tax\Collection\TaxAreaRuleTranslationDetailCollection;
use Shopware\Api\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxAreaRuleTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleTranslationDetailCollection
     */
    protected $taxAreaRuleTranslations;

    public function __construct(TaxAreaRuleTranslationDetailCollection $taxAreaRuleTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getTaxAreaRuleTranslations(): TaxAreaRuleTranslationDetailCollection
    {
        return $this->taxAreaRuleTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->taxAreaRuleTranslations->getTaxAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->taxAreaRuleTranslations->getTaxAreaRules(), $this->context);
        }
        if ($this->taxAreaRuleTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->taxAreaRuleTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
