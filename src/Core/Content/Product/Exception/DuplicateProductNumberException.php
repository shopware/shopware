<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class DuplicateProductNumberException extends ShopwareHttpException
{
    public function __construct(
        string $number,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Product with number "{{ number }}" already exists.',
            ['number' => $number],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_PRODUCT_NUMBER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
