<?php declare(strict_types=1);

namespace Shopware\Core\Content\Breadcrumb\Exception;

use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * A product without a main category in the sales channel has no breadcrumb. Callers that treat that
 * as an empty path can catch this exception directly instead of comparing error codes.
 */
#[Package('inventory')]
class CategoryNotFoundForProductException extends BreadcrumbException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            Response::HTTP_NO_CONTENT,
            self::BREADCRUMB_CATEGORY_NOT_FOUND,
            'The main category for product {{ productId }} is not found',
            ['productId' => $productId]
        );
    }
}
