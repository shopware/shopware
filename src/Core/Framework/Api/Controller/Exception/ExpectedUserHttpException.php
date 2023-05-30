<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ExpectedUserHttpException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('For this interaction an authenticated user login is required.');
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
