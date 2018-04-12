<?php declare(strict_types=1);

namespace Shopware\Context\Rule\LineItem;

use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Context\Rule\Match;
use Shopware\Context\Struct\StorefrontContext;

class LineItemTypeRule extends LineItemRule
{
    /**
     * @var string
     */
    protected $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     */
    public function match(
        CalculatedLineItem $calculatedLineItem,
        StorefrontContext $context
    ): Match {
        return new Match(
            $calculatedLineItem->getType() == $this->type,
            ['LineItem type does not match']
        );
    }
}
