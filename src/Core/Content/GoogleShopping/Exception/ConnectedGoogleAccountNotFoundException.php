<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ConnectedGoogleAccountNotFoundException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'This sales channel is not connect to any google account'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_CONNECTED_ACCOUNT_NOT_FOUND';
    }
}
