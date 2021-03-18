<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class ShippingMethodRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $shippingMethodIds;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        $shippingMethodId = $scope->getSalesChannelContext()->getShippingMethod()->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($shippingMethodId, $this->shippingMethodIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($shippingMethodId, $this->shippingMethodIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'shippingMethodIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'shippingMethod';
    }
}
