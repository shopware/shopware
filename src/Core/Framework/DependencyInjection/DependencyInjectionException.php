<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class DependencyInjectionException extends HttpException
{
    public const PROJECT_DIR_IS_NOT_A_STRING = 'FRAMEWORK__PROJECT_DIR_IS_NOT_A_STRING';
    public const BUNDLES_METADATA_IS_NOT_AN_ARRAY = 'FRAMEWORK__BUNDLES_METADATA_IS_NOT_AN_ARRAY';

    public static function projectDirNotInContainer(): self
    {
        return new self(
            500,
            self::PROJECT_DIR_IS_NOT_A_STRING,
            'Container parameter "kernel.project_dir" needs to be a string'
        );
    }

    public static function bundlesMetadataIsNotAnArray(): self
    {
        return new self(
            500,
            self::BUNDLES_METADATA_IS_NOT_AN_ARRAY,
            'Container parameter "kernel.bundles_metadata" needs to be an array'
        );
    }
}
