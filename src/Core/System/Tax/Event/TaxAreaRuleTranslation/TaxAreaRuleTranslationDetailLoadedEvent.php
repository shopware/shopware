<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Application\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\System\Tax\Collection\TaxAreaRuleTranslationDetailCollection;
use Shopware\System\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class TaxAreaRuleTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var TaxAreaRuleTranslationDetailCollection
     */
    protected $taxAreaRuleTranslations;

    public function __construct(TaxAreaRuleTranslationDetailCollection $taxAreaRuleTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->taxAreaRuleTranslations = $taxAreaRuleTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
            $events[] = new LanguageBasicLoadedEvent($this->taxAreaRuleTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
