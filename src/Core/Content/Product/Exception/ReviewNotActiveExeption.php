<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ReviewNotActiveExeption extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Reviews not activated');
    }

    public function getErrorCode(): string
    {
        return 'PRODUCT__REVIEW_NOT_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
