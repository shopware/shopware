<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class CartVolumeRule extends Rule
{
    protected ?float $volume;

    protected string $operator;

    /**
     * @internal
     */
    public function __construct(string $operator = self::OPERATOR_EQ, ?float $volume = null)
    {
        parent::__construct();

        $this->operator = $operator;
        $this->volume = $volume;
    }

    public function getName(): string
    {
        return 'cartVolume';
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope) {
            return false;
        }

        if ($this->volume === null) {
            throw new UnsupportedValueException(\gettype($this->volume), self::class);
        }

        return RuleComparison::numeric($this->calculateCartVolume($scope->getCart()), $this->volume, $this->operator);
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
            if ($delivery instanceof Delivery) {
                $volume += $delivery->getPositions()->getVolume();
            }
        }

        return $volume;
    }
}
