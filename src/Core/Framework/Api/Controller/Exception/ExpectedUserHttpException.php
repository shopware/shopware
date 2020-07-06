<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ExpectedUserHttpException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Expected user, got login from integration.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__EXPECTED_USER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
