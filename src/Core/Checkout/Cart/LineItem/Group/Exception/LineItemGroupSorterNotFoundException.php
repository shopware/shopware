<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LineItemGroupSorterNotFoundException extends ShopwareHttpException
{
    public function __construct(string $key)
    {
        parent::__construct('Sorter "{{ key }}" has not been found!', ['key' => $key]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__GROUP_SORTER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
