<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextRule;

use Shopware\Application\Context\Collection\ContextRuleBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ContextRuleBasicCollection
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
