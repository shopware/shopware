<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class SeoUrlRouteNotFoundException extends ShopwareHttpException
{
    final public const ERROR_CODE = 'FRAMEWORK__SEO_URL_ROUTE_NOT_FOUND';

    public function __construct(string $routeName)
    {
        parent::__construct('seo url route"{{ routeName }}" not found.', ['routeName' => $routeName]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
