<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection
     */
    protected $contextRules;

    public function __construct(ContextRuleBasicCollection $contextRules, ApplicationContext $context)
    {
        $this->context = $context;
        $this->contextRules = $contextRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getContextRules(): ContextRuleBasicCollection
    {
        return $this->contextRules;
    }
}
