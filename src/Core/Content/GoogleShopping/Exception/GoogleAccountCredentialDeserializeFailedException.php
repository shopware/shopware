<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class GoogleAccountCredentialDeserializeFailedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Failed to deserialize google account credential.');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_ACCOUNT_CREDENTIAL_DESERIALIZE_FAILED';
    }
}
