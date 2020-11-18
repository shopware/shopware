<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InsufficientPermissionException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Insufficient permission exception.');
    }

    public function getErrorCode(): string
    {
        return 'INSUFFICIENT_PERMISSION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
