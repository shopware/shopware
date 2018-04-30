<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextRule;

use Shopware\Api\Context\Struct\ContextRuleSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.search.result.loaded';

    /**
     * @var ContextRuleSearchResult
     */
    protected $result;

    public function __construct(ContextRuleSearchResult $result)
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
