<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class InvalidPriceImportPayloadException extends ShopwareHttpException
{
    public function __construct(array $errors)
    {
        parent::__construct(
            'Invalid price import payload',
            ['errors' => $errors]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_VARIANT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
