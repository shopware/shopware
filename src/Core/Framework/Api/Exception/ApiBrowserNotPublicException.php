<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ApiBrowserNotPublicException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Not allowed.'
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_BROWSER_NOT_PUBLIC';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
