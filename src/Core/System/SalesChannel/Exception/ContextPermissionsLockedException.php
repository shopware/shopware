<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ContextPermissionsLockedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Context permission in SalesChannel context already locked.');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CONTEXT_PERMISSIONS_LOCKED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
