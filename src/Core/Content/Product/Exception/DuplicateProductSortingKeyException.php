<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class DuplicateProductSortingKeyException extends ShopwareHttpException
{
    public function __construct(
        string $key,
        \Throwable $e
    ) {
        parent::__construct(
            'Sorting with key "{{ key }}" already exists.',
            ['key' => $key],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_PRODUCT_SORTING_KEY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
