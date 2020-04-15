<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidGoogleAuthorizationCodeException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Please provide valid authorization code'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_INVALID_AUTHORIZATION_CODE';
    }
}
