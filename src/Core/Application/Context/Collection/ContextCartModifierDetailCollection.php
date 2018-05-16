<?php declare(strict_types=1);

namespace Shopware\Application\Context\Collection;

use Shopware\Application\Context\Struct\ContextCartModifierDetailStruct;
use Shopware\Checkout\Rule\Collection\ContextRuleBasicCollection;

class ContextCartModifierDetailCollection extends ContextCartModifierBasicCollection
{
    /**
     * @var ContextCartModifierDetailStruct[]
     */
    protected $elements = [];

    public function getContextRules(): ContextRuleBasicCollection
    {
        return new ContextRuleBasicCollection(
            $this->fmap(function (ContextCartModifierDetailStruct $contextCartModifier) {
                return $contextCartModifier->getContextRule();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ContextCartModifierDetailStruct::class;
    }
}
