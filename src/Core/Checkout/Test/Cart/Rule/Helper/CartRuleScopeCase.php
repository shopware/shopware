<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule\Helper;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('business-ops')]
class CartRuleScopeCase
{
    /**
     * @param LineItem[] $lineItems
     */
    public function __construct(
        public string $description,
        public bool $match,
        public LineItemPropertyRule $rule,
        public array $lineItems
    ) {
    }
}
