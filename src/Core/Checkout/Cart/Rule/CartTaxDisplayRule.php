<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @package business-ops
 */
class CartTaxDisplayRule extends Rule
{
    protected string $taxDisplay;

    /**
     * @internal
     */
    public function __construct(string $taxDisplay = CartPrice::TAX_STATE_GROSS)
    {
        parent::__construct();
        $this->taxDisplay = $taxDisplay;
    }

    public function match(RuleScope $scope): bool
    {
        return $this->taxDisplay === $scope->getSalesChannelContext()->getTaxState();
    }

    public function getConstraints(): array
    {
        return [
            'taxDisplay' => RuleConstraints::string(),
        ];
    }

    public function getName(): string
    {
        return 'cartTaxDisplay';
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->selectField('taxDisplay', [CartPrice::TAX_STATE_GROSS, CartPrice::TAX_STATE_NET]);
    }
}
