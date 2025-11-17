<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token\Constraint;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PaymentTokenRegistered extends Constraint
{
    final public const PAYMENT_TOKEN_NOT_REGISTERED = '4b8c09e2-87cb-4a0e-bcf1-bbc9aa805af5';

    protected const ERROR_NAMES = [
        self::PAYMENT_TOKEN_NOT_REGISTERED => 'PAYMENT_TOKEN_NOT_REGISTERED',
    ];

    protected string $message;

    /**
     * @internal
     */
    #[HasNamedArguments]
    public function __construct(
        string $message = 'The payment token with id {{ id }} is not registered.'
    ) {
        $this->message = $message;

        parent::__construct();
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
