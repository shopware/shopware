<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidGoogleIdToken extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Google id token is invalid.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_ACCOUNT_ID_TOKEN_INVALID';
    }
}
