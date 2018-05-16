<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentMethodNotFoundHttpException extends HttpException
{
    public const CODE = 4007;

    public function __construct(string $id)
    {
        parent::__construct(400, sprintf('Payment method with id %s not found', $id), null, [], self::CODE);
    }
}
