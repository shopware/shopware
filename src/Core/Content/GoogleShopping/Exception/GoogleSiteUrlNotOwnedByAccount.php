<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class GoogleSiteUrlNotOwnedByAccount extends ShopwareHttpException
{
    public function __construct(string $siteUrl)
    {
        parent::__construct(
            "This website url $siteUrl is not owned by this account."
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__GOOGLE_SHOPPING_SITE_URL_IS_NOT_OWNED_BY_ACCOUNT';
    }
}
