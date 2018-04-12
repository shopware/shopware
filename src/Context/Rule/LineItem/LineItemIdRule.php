<?php declare(strict_types=1);

namespace Shopware\Context\Rule\LineItem;

use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Context\Rule\Match;
use Shopware\Context\Struct\StorefrontContext;

class LineItemIdRule extends LineItemRule
{
    /**
     * @var string
     */
    protected $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Validate the current rule and returns a reason object which contains defines if the rule match and if not why not
     */
    public function match(
        CalculatedLineItem $calculatedLineItem,
        StorefrontContext $context
    ): Match {
        return new Match(
            $calculatedLineItem->getIdentifier() == $this->id,
            ['LineItem id does not match']
        );
    }
}
