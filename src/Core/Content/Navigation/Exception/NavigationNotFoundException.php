<?php declare(strict_types=1);

namespace Shopware\Core\Content\Navigation\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class NavigationNotFoundException extends ShopwareHttpException
{
    public const CODE = 400000;

    public function __construct(string $navigationId, int $code = self::CODE, \Throwable $previous = null)
    {
        $message = sprintf('Navigation for id %s not found', $navigationId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
