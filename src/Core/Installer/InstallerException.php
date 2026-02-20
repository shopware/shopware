<?php

declare(strict_types=1);

namespace Shopware\Core\Installer;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class InstallerException extends HttpException
{
    final public const INVALID_REQUIREMENT_CHECK = 'INSTALLER__INVALID_REQUIREMENT_CHECK';

    public static function invalidRequirementCheck(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_REQUIREMENT_CHECK,
            $message,
        );
    }
}
