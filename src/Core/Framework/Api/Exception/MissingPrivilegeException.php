<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MissingPrivilegeException extends AccessDeniedHttpException
{
    public function __construct(string $privilege)
    {
        parent::__construct(sprintf('Missing privilege %s', $privilege));
    }
}
