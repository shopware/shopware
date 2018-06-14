<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Content\Rule\Struct\RuleSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class RuleSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'rule.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Rule\Struct\RuleSearchResult
     */
    protected $result;

    public function __construct(RuleSearchResult $result)
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
