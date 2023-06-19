<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::pluginPaymentMethodDeleteRestriction instead
 */
class PluginPaymentMethodsDeleteRestrictionException extends PaymentException
{
    public function __construct(?\Throwable $e = null)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CHECKOUT__PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION',
            'Plugin payment methods can not be deleted via API.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
