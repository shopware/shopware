<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PluginPaymentMethodsDeleteRestrictionException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Plugin payment methods can not be deleted via API.');
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
