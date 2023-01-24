<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule\Helper;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;

/**
 * @package business-ops
 *
 * @internal
 */
class CartRuleScopeCase
{
    /**
     * @param LineItem[] $lineItems
     */
    public function __construct(public string $description, public bool $match, public LineItemPropertyRule $rule, public array $lineItems)
    {
    }
}
