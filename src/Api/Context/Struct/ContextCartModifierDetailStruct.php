<?php declare(strict_types=1);

namespace Shopware\Api\Context\Struct;

class ContextCartModifierDetailStruct extends ContextCartModifierBasicStruct
{
    /**
     * @var ContextRuleBasicStruct
     */
    protected $contextRule;

    public function getContextRule(): ContextRuleBasicStruct
    {
        return $this->contextRule;
    }

    public function setContextRule(ContextRuleBasicStruct $contextRule): void
    {
        $this->contextRule = $contextRule;
    }
}
