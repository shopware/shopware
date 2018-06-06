<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\Struct\ContextRuleSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ContextRuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Rule\Struct\ContextRuleSearchResult
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
