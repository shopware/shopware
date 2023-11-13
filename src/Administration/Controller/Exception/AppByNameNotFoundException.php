<?php declare(strict_types=1);

namespace Shopware\Administration\Controller\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('administration')]
class AppByNameNotFoundException extends ShopwareHttpException
{
    public function __construct(string $appName)
    {
        parent::__construct(
            'The provided name {{ name }} is invalid and no app could be found.',
            ['name' => $appName]
        );
    }

    public function getErrorCode(): string
    {
        return 'ADMINISTRATION__APP_BY_NAME_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
