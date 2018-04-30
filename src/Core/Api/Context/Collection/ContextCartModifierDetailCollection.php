<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextCartModifierDetailStruct;

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
