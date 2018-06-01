<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection
     */
    protected $contextRules;

    public function __construct(ContextRuleBasicCollection $contextRules, Context $context)
    {
        $this->context = $context;
        $this->contextRules = $contextRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getContextRules(): ContextRuleBasicCollection
    {
        return $this->contextRules;
    }
}
