<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class FilterSorterNotFoundException extends ShopwareHttpException
{
    public function __construct(string $key)
    {
        parent::__construct('Sorter "{{ key }}" has not been found!', ['key' => $key]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__FILTER_SORTER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
