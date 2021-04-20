<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingPrivilegeException extends ShopwareHttpException
{
    public const MISSING_PRIVILEGE_ERROR = 'FRAMEWORK__MISSING_PRIVILEGE_ERROR';

    public function __construct(array $privilege = [], ?\Throwable $previous = null)
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privilege,
        ]);

        parent::__construct($errorMessage ?: '', [], $previous);
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
