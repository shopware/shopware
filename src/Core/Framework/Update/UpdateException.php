<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class UpdateException extends HttpException
{
    public const AUTO_UPDATE_DISABLED = 'FRAMEWORK__AUTO_UPDATE_DISABLED';
    public const UPDATE_MODULE_HIDDEN = 'FRAMEWORK__UPDATE_MODULE_HIDDEN';
    public const CLUSTER_SETUP_NOT_SUPPORTED = 'FRAMEWORK__UPDATE_CLUSTER_SETUP_NOT_SUPPORTED';
    public const MISSING_USER = 'FRAMEWORK__UPDATE_MISSING_USER';
    public const MISSING_VERSION = 'FRAMEWORK__UPDATE_MISSING_VERSION';

    public static function autoUpdateDisabled(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::AUTO_UPDATE_DISABLED,
            'Auto update is disabled'
        );
    }

    public static function clusterSetupNotSupported(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CLUSTER_SETUP_NOT_SUPPORTED,
            'Updating through the Administration is not possible on cluster setups'
        );
    }

    public static function updateModuleHidden(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::UPDATE_MODULE_HIDDEN,
            'The update module is hidden'
        );
    }

    public static function missingUser(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_USER,
            'The current request does not contain a valid user'
        );
    }

    public static function missingVersion(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_VERSION,
            'The request is missing a valid version parameter'
        );
    }
}
