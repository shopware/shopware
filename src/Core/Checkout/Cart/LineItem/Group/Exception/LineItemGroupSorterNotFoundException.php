<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - will be removed, use Shopware\Core\Checkout\Cart\CartException::lineItemGroupSorterNotFoundException instead
 */
#[Package('checkout')]
class LineItemGroupSorterNotFoundException extends ShopwareHttpException
{
    public function __construct(string $key)
    {
        parent::__construct('Sorter "{{ key }}" has not been found!', ['key' => $key]);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Use Shopware\Core\Checkout\Cart\CartException::lineItemGroupSorterNotFoundException instead');

        return 'CHECKOUT__GROUP_SORTER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Use Shopware\Core\Checkout\Cart\CartException::lineItemGroupSorterNotFoundException instead');

        return Response::HTTP_BAD_REQUEST;
    }
}
