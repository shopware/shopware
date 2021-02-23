<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

class PaymentMethodRule extends Rule
{
    /**
     * @var string[]
     */
    protected array $paymentMethodIds;

    protected string $operator;

    public function match(RuleScope $scope): bool
    {
        $paymentMethodId = $scope->getSalesChannelContext()->getPaymentMethod()->getId();

        switch ($this->operator) {
            case self::OPERATOR_EQ:
                return \in_array($paymentMethodId, $this->paymentMethodIds, true);

            case self::OPERATOR_NEQ:
                return !\in_array($paymentMethodId, $this->paymentMethodIds, true);

            default:
                throw new UnsupportedOperatorException($this->operator, self::class);
        }
    }

    public function getConstraints(): array
    {
        return [
            'paymentMethodIds' => [new NotBlank(), new ArrayOfUuid()],
            'operator' => [new NotBlank(), new Choice([self::OPERATOR_EQ, self::OPERATOR_NEQ])],
        ];
    }

    public function getName(): string
    {
        return 'paymentMethod';
    }
}
