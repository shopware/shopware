<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class MissingPrivilegeException extends ShopwareHttpException
{
    final public const MISSING_PRIVILEGE_ERROR = 'FRAMEWORK__MISSING_PRIVILEGE_ERROR';

    public function __construct(array $privilege = [])
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privilege,
        ], \JSON_THROW_ON_ERROR);

        parent::__construct($errorMessage ?: '');
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getErrorCode(): string
    {
        return self::MISSING_PRIVILEGE_ERROR;
    }
}
