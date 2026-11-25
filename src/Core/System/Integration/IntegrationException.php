<?php declare(strict_types=1);

namespace Shopware\Core\System\Integration;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@framework')]
class IntegrationException extends HttpException
{
    final public const ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED = 'INTEGRATION__ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED';

    public static function adminIntegrationChangeNotAllowed(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED,
            'An admin integration can only be changed or deleted by an admin.'
        );
    }
}
