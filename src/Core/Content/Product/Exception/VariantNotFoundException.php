<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class VariantNotFoundException extends ShopwareHttpException
{
    public function __construct(string $productId, array $options)
    {
        parent::__construct(
            'Variant for productId {{ productId }} with options {{ options }} not found.',
            [
                'productId' => $productId,
                'options' => json_encode($options),
            ]
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
