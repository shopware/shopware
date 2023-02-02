<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class LanguageOfOrderDeleteException extends ShopwareHttpException
{
    public function __construct(?\Throwable $e = null)
    {
        parent::__construct('The language is still linked in some orders.', [], $e);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__LANGUAGE_OF_ORDER_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
