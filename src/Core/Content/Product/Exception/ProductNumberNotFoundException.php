<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ProductNumberNotFoundException extends ShopwareHttpException
{
    public function __construct(string $number, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Product with number "{{ number }}" not found.',
            ['number' => $number],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
