<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class CartVolumeRule extends Rule
{
    final public const RULE_NAME = 'cartVolume';

    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $volume = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if ($this->volume === null) {
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw new UnsupportedValueException(\gettype($this->volume), self::class);
            }
            throw CartException::unsupportedValue(\gettype($this->volume), self::class);
        }

        return RuleComparison::numeric($this->calculateCartVolume($scope->getCart()), $this->volume * self::VOLUME_FACTOR, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'volume' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->numberField('volume', ['unit' => RuleConfig::UNIT_VOLUME]);
    }

    private function calculateCartVolume(Cart $cart): float
    {
        $volume = 0.0;

        foreach ($cart->getDeliveries() as $delivery) {
            $volume += $delivery->getPositions()->getVolume();
        }

        return $volume;
    }
}
