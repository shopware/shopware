<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token\Constraint;

use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[Package('framework')]
class PaymentTokenRegisteredValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PaymentTokenLifecycle $paymentTokenLifecycle,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PaymentTokenRegistered) {
            throw PaymentException::unexpectedConstraintType($constraint, PaymentTokenRegistered::class);
        }

        if (!\is_string($value) || $value === '') {
            return;
        }

        if ($this->paymentTokenLifecycle->isRegistered($value)) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ id }}', $this->formatValue($value))
            ->setCode(PaymentTokenRegistered::PAYMENT_TOKEN_NOT_REGISTERED)
            ->addViolation();
    }
}
