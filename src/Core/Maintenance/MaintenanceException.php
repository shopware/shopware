<?php

declare(strict_types=1);

namespace Shopware\Core\Maintenance;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Maintenance\System\Exception\DatabaseSetupException;
use Shopware\Core\Maintenance\System\Exception\JwtCertificateGenerationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class MaintenanceException extends HttpException
{
    final public const MAINTENANCE_SYMFONY_CONSOLE_APPLICATION_NOT_FOUND = 'MAINTENANCE__SYMFONY_CONSOLE_APPLICATION_NOT_FOUND';
    final public const MAINTENANCE_MIGRATION_INVALID_VERSION_SELECTION_MODE = 'MAINTENANCE__MIGRATION_INVALID_VERSION_SELECTION_MODE';
    final public const MAINTENANCE_ENVIRONMENT_VARIABLE_NOT_DEFINED = 'MAINTENANCE__ENVIRONMENT_VARIABLE_NOT_DEFINED';
    final public const MAINTENANCE_ENVIRONMENT_VARIABLE_NOT_VALID = 'MAINTENANCE__ENVIRONMENT_VARIABLE_NOT_VALID';
    final public const MAINTENANCE_DB_CONNECTION_PARAMETER_MISSING = 'MAINTENANCE__DB_CONNECTION_PARAMETER_MISSING';
    final public const MAINTENANCE_DB_VERSION_SELECT_FAILED = 'MAINTENANCE__DB_VERSION_SELECT_FAILED';
    final public const MAINTENANCE_SHOP_CONFIGURATION_NOT_VALID = 'MAINTENANCE__SHOP_CONFIGURATION_NOT_VALID';
    final public const MAINTENANCE_COULD_NOT_GET_ID = 'MAINTENANCE__COULD_NOT_GET_ID_OF_ENTITY';
    final public const MAINTENANCE_USER_ALREADY_EXISTS = 'MAINTENANCE__USER_ALREADY_EXISTS';
    final public const MAINTENANCE_USER_PASSWORD_TOO_SHORT = 'MAINTENANCE__USER_PASSWORD_TOO_SHORT';
    final public const MAINTENANCE_COULD_NOT_READ_FILE = 'MAINTENANCE__COULD_NOT_READ_FILE';
    final public const MAINTENANCE_COULD_NOT_CREATE_DIRECTORY = 'MAINTENANCE__COULD_NOT_CREATE_DIRECTORY';

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

    public static function environmentVariableNotDefined(string $variableName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_ENVIRONMENT_VARIABLE_NOT_DEFINED,
            'Environment variable "{{ variableName }}" is not defined.',
            ['variableName' => $variableName]
        );
    }

    public static function environmentVariableNotValid(string $variableName, string $actualValue, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_ENVIRONMENT_VARIABLE_NOT_VALID,
            'Environment variable "{{ variableName }}" with value "{{ actualValue }}" is not valid: {{ reason }}.',
            ['variableName' => $variableName, 'actualValue' => $actualValue, 'reason' => $reason]
        );
    }

    public static function dbConnectionParameterMissing(string $parameterName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_DB_CONNECTION_PARAMETER_MISSING,
            'Provided database connection information is not valid. Missing parameter "{{ parameterName }}"',
            ['parameterName' => $parameterName]
        );
    }

    public static function dbVersionSelectFailed(): DatabaseSetupException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new DatabaseSetupException('Failed to select database version');
        }

        return new DatabaseSetupException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_DB_VERSION_SELECT_FAILED,
            'Failed to select database version'
        );
    }

    public static function dbVersionMismatch(
        string $dbKind,
        string $actualVersion,
        string $mysqlRequiredVersion,
        string $mariaDBRequiredVersion
    ): DatabaseSetupException {
        if (!Feature::isActive('v6.7.0.0')) {
            return new DatabaseSetupException(
                \sprintf(
                    'Your database server is running %s %s, but Shopware 6 requires at least MySQL %s OR MariaDB %s',
                    $dbKind,
                    $actualVersion,
                    $mysqlRequiredVersion,
                    $mariaDBRequiredVersion
                )
            );
        }

        return new DatabaseSetupException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_DB_VERSION_SELECT_FAILED,
            'Your database server is running {{ dbKind }} {{ actualVersion }}, but Shopware 6 requires at least MySQL {{ mysqlRequiredVersion }} OR MariaDB {{ mariaDBRequiredVersion }}',
            [
                'dbKind' => $dbKind,
                'actualVersion' => $actualVersion,
                'mysqlRequiredVersion' => $mysqlRequiredVersion,
                'mariaDBRequiredVersion' => $mariaDBRequiredVersion,
            ]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed without replacement as the class where this exception is thrown will be removed
     */
    public static function jwtCertificateGenerationFailed(string $message): JwtCertificateGenerationException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0')
        );

        return new JwtCertificateGenerationException($message);
    }

    public static function shopConfigurationNotValid(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_SHOP_CONFIGURATION_NOT_VALID,
            $message
        );
    }

    public static function couldNotGetId(string $entity): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_COULD_NOT_GET_ID,
            'Could not get ID of {{ entity }}',
            ['entity' => $entity]
        );
    }

    public static function userAlreadyExists(string $username): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAINTENANCE_USER_ALREADY_EXISTS,
            'User with username "{{ username }}" already exists.',
            ['username' => $username]
        );
    }

    public static function passwordTooShort(int $minPasswordLength): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAINTENANCE_USER_PASSWORD_TOO_SHORT,
            'The password must have at least {{ minPasswordLength }} characters.',
            ['minPasswordLength' => $minPasswordLength]
        );
    }

    public static function couldNotReadFile(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_COULD_NOT_READ_FILE,
            'Could not read file from path "{{ path }}"',
            ['path' => $path]
        );
    }

    public static function couldNotCreateDirectory(string $directoryName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAINTENANCE_COULD_NOT_CREATE_DIRECTORY,
            'Could not create directory "{{ directoryName }}"',
            ['directoryName' => $directoryName]
        );
    }
}
