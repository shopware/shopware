<?php declare(strict_types=1);

namespace Shopware\Tax\Event\TaxAreaRuleTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Tax\Struct\TaxAreaRuleTranslationSearchResult;

class TaxAreaRuleTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'tax_area_rule_translation.search.result.loaded';

    /**
     * @var TaxAreaRuleTranslationSearchResult
     */
    protected $result;

    public function __construct(TaxAreaRuleTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
