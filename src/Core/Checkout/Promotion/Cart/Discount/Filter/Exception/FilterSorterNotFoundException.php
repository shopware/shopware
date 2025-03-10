<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception;

use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use PromotionException::filterSorterNotFound() instead
 */
#[Package('checkout')]
class FilterSorterNotFoundException extends PromotionException
{
    public function __construct(string $key)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            parent::FILTER_SORTER_NOT_FOUND,
            'Sorter "{{ key }}" has not been found!',
            ['key' => $key]
        );
    }
}
