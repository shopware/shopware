<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Event;

use Shopware\Core\Content\Rule\Collection\RuleBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class RuleBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'rule.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Content\Rule\Collection\RuleBasicCollection
     */
    protected $rules;

    public function __construct(RuleBasicCollection $rules, Context $context)
    {
        $this->context = $context;
        $this->rules = $rules;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRules(): RuleBasicCollection
    {
        return $this->rules;
    }
}
