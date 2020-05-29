<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DatafeedNotFoundException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Datafeed not found'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_DATA_FEED_NOT_FOUND';
    }
}
