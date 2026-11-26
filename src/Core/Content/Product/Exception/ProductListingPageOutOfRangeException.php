<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductListingPageOutOfRangeException extends ProductException
{
    public function __construct(
        int $requestedPage,
        int $lastPage
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::LISTING_PAGE_OUT_OF_RANGE,
            'Requested listing page {{ requestedPage }} is out of range (last page: {{ lastPage }}).',
            ['requestedPage' => $requestedPage, 'lastPage' => $lastPage]
        );
    }
}
