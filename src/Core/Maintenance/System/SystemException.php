<?php

declare(strict_types=1);

namespace Shopware\Core\Maintenance\System;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class SystemException extends HttpException
{
    final public const MAINTENANCE_SYMFONY_CONSOLE_APPLICATION_NOT_FOUND = 'MAINTENANCE__SYMFONY_CONSOLE_APPLICATION_NOT_FOUND';

    final public const MAINTENANCE_MIGRATION_INVALID_VERSION_SELECTION_MODE = 'MAINTENANCE__MIGRATION_INVALID_VERSION_SELECTION_MODE';

    public static function consoleApplicationNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_SYMFONY_CONSOLE_APPLICATION_NOT_FOUND,
            'Symfony console application not found'
        );
    }

    public static function invalidVersionSelectionMode(string $mode): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_MIGRATION_INVALID_VERSION_SELECTION_MODE,
            'Version selection mode needs to be one of these values: "{{ validModes }}", but "{{ mode }}" was given.',
            [
                'validModes' => implode('", "', MigrationCollectionLoader::VALID_VERSION_SELECTION_SAFE_VALUES),
                'mode' => $mode,
            ]
        );
    }
}
