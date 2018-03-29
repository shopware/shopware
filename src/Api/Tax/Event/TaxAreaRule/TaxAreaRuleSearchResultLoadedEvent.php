<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\TaxAreaRule;

use Shopware\Api\Tax\Struct\TaxAreaRuleSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class TaxAreaRuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'tax_area_rule.search.result.loaded';

    /**
     * @var TaxAreaRuleSearchResult
     */
    protected $result;

    public function __construct(TaxAreaRuleSearchResult $result)
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
