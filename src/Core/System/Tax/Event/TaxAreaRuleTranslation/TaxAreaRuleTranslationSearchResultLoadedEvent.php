<?php declare(strict_types=1);

namespace Shopware\System\Tax\Event\TaxAreaRuleTranslation;

use Shopware\System\Tax\Struct\TaxAreaRuleTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class TaxAreaRuleTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule_translation.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
