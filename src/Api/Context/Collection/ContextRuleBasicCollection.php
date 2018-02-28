<?php declare(strict_types=1);

namespace Shopware\Api\Context\Collection;

use Shopware\Api\Context\Struct\ContextRuleBasicStruct;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Struct\StorefrontContext;

class ContextRuleBasicCollection extends EntityCollection
{
    /**
     * @var ContextRuleBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ContextRuleBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ContextRuleBasicStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ContextRuleBasicStruct::class;
    }

    public function filterMatchingRules(CalculatedCart $cart, StorefrontContext $context)
    {
        return $this->filter(
            function(ContextRuleBasicStruct $rule) use ($cart, $context) {
                return $rule->getPayload()->match($cart, $context)->matches();
            }
        );
    }
}
