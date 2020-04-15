<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelIsNotGoogleShoppingTypeException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'This sales channel is not google shopping sales channel.'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SALES_CHANNEL_IS_NOT_GOOGLE_SHOPPING_TYPE';
    }
}
