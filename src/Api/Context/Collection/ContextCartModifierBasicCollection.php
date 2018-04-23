<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextCartModifierBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class ContextCartModifierBasicCollection extends EntityCollection
{
    /**
     * @var ContextCartModifierBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ContextCartModifierBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ContextCartModifierBasicStruct
    {
        return parent::current();
    }

    // todo
//    public function filterMatchingRules(CalculatedCart $cart, StorefrontContext $context)
//    {
//        return $this->filter(
//            function (ContextRuleBasicStruct $rule) use ($cart, $context) {
//                return $rule->getPayload()->match($cart, $context)->matches();
//            }
//        );
//    }

    protected function getExpectedClass(): string
    {
        return ContextCartModifierBasicStruct::class;
    }
}
