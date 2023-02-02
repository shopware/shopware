<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidDomainException extends ShopwareHttpException
{
    public function __construct(string $domain)
    {
        parent::__construct('Invalid domain \'{{ domain }}\'', ['domain' => $domain]);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__INVALID_DOMAIN';
    }
}
