<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Collection;

use Shopware\Core\Application\Context\Struct\ContextCartModifierDetailStruct;
use Shopware\Core\Checkout\Rule\Collection\ContextRuleBasicCollection;

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
