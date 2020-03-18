<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AlreadyConnectedGoogleMerchantAccountException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'This sales channel is already connect to a google merchant account'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_ALREADY_CONNECTED_MERCHANT_ACCOUNT';
    }
}
