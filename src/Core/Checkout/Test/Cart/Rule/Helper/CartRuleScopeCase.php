<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule\Helper;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;

class CartRuleScopeCase
{
    public string $description;

    public bool $match;

    public LineItemPropertyRule $rule;

    /**
     * @var LineItem[]
     */
    public array $lineItems;

    /**
     * @param LineItem[] $lineItems
     */
    public function __construct(string $description, bool $match, LineItemPropertyRule $rule, array $lineItems)
    {
        $this->match = $match;
        $this->rule = $rule;
        $this->lineItems = $lineItems;
        $this->description = $description;
    }
}
