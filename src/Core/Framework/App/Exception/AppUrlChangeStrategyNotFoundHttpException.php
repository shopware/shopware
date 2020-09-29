<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AppUrlChangeStrategyNotFoundHttpException extends ShopwareHttpException
{
    public function __construct(AppUrlChangeStrategyNotFoundException $previous)
    {
        parent::__construct($previous->getMessage(), [], $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__APP_URL_CHANGE_RESOLVER_NOT_FOUND';
    }
}
