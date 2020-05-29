<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelIsNotLinkedToProductExport extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'This sales channel has no default product export linked'
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SALES_CHANNEL_IS_NOT_LINKED_TO_PRODUCT_EXPORT';
    }
}
