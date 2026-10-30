<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use ProductException::reviewNotActive instead
 */
#[Package('inventory')]
class ReviewNotActiveExeption extends ShopwareHttpException
{
    public function __construct()
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct('Reviews not activated');
    }

    public function getErrorCode(): string
    {
        return 'PRODUCT__REVIEW_NOT_ACTIVE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
