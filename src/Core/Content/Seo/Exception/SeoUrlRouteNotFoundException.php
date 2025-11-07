<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Exception;

use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class SeoUrlRouteNotFoundException extends SeoException
{
    final public const ERROR_CODE = 'CONTENT__SEO_URL_ROUTE_NOT_FOUND';

    public function __construct(string $routeName)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::ERROR_CODE,
            self::$couldNotFindMessage,
            ['entity' => 'SEO URL route', 'field' => 'name', 'value' => $routeName]
        );
    }
}
