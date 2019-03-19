<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class NavigationNotFoundException extends ShopwareHttpException
{
    public function __construct(string $navigationId)
    {
        parent::__construct(
            'Navigation for id {{ navigationId }} not found.',
            ['navigationId' => $navigationId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__NAVIGATION_NOT_FOUND';
    }
}
