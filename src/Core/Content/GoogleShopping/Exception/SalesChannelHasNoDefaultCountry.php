<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelHasNoDefaultCountry extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'This sales channel has no default country specified'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SALES_CHANNEL_HAS_NO_DEFAULT_COUNTRY';
    }
}
