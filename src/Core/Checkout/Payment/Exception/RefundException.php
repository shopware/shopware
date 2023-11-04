<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class RefundException extends RefundProcessException
{
    public function __construct(
        string $refundId,
        string $errorMessage,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            $refundId,
            'The refund process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__REFUND_PROCESS_INTERRUPTED';
    }
}
