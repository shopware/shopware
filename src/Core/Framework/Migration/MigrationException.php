<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class MigrationException extends HttpException
{
    final public const FRAMEWORK_MIGRATION_INVALID_VERSION_SELECTION_MODE = 'FRAMEWORK__MIGRATION_INVALID_VERSION_SELECTION_MODE';

    public static function invalidVersionSelectionMode(string $mode): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_INVALID_VERSION_SELECTION_MODE,
            'Version selection mode needs to be one of these values: "{{ validModes }}", but "{{ mode }}" was given.',
            [
                'validModes' => implode('", "', MigrationCollectionLoader::VALID_VERSION_SELECTION_SAFE_VALUES),
                'mode' => $mode,
            ]
        );
    }
}
