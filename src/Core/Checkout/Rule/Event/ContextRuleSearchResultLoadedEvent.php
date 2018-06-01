<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Rule\Struct\ContextRuleSearchResult;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.search.result.loaded';

    /**
     * @var \Shopware\Checkout\Rule\Struct\ContextRuleSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
