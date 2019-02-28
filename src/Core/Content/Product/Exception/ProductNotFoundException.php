<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ProductNotFoundException extends ShopwareHttpException
{
    protected $code = 'PRODUCT-NOT-FOUND';

    public function __construct(string $productId, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Product for id %s not found', $productId);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
