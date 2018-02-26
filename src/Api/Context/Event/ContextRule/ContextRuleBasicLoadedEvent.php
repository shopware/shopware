<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextRule;

use Shopware\Api\Context\Collection\ContextRuleBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ContextRuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_rule.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ContextRuleBasicCollection
     */
    protected $contextRules;

    public function __construct(ContextRuleBasicCollection $contextRules, ShopContext $context)
    {
        $this->context = $context;
        $this->contextRules = $contextRules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getContextRules(): ContextRuleBasicCollection
    {
        return $this->contextRules;
    }
}
