<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MissingPrivilegeException extends AccessDeniedHttpException
{
    public const MISSING_PRIVILEGE_ERROR = 'FRAMEWORK__MISSING_PRIVILEGE_ERROR';

    public function __construct(array $privilege = [])
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privilege,
        ]);

        if ($errorMessage === false) {
            $errorMessage = null;
        }

        parent::__construct($errorMessage);
    }

    public function getErrorCode(): string
    {
        return self::MISSING_PRIVILEGE_ERROR;
    }
}
