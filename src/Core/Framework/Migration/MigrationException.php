<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Exception\UnknownMigrationSourceException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
/**
 * @codeCoverageIgnore
 */
class MigrationException extends HttpException
{
    final public const FRAMEWORK_MIGRATION_INVALID_VERSION_SELECTION_MODE = 'FRAMEWORK__MIGRATION_INVALID_VERSION_SELECTION_MODE';
    final public const FRAMEWORK_MIGRATION_INVALID_MIGRATION_SOURCE = 'FRAMEWORK__INVALID_MIGRATION_SOURCE';
    final public const FRAMEWORK_MIGRATION_IMPLAUSIBLE_CREATION_TIMESTAMP = 'FRAMEWORK__MIGRATION_IMPLAUSIBLE_CREATION_TIMESTAMP';
    final public const FRAMEWORK_MIGRATION_PLUGIN_COULD_NOT_BE_FOUND = 'FRAMEWORK__MIGRATION_PLUGIN_COULD_NOT_BE_FOUND';
    final public const FRAMEWORK_MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND = 'FRAMEWORK__MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND';
    final public const FRAMEWORK_MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED = 'FRAMEWORK__MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED';
    final public const INVALID_ARGUMENT = 'FRAMEWORK__MIGRATION_INVALID_ARGUMENT_EXCEPTION';

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function invalidArgument(string $message): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \InvalidArgumentException($message);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ARGUMENT,
            $message
        );
    }

    public static function invalidVersionSelectionMode(string $mode): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_INVALID_VERSION_SELECTION_MODE,
            'Version selection mode needs to be one of these values: "{{ validModes }}", but "{{ mode }}" was given.',
            [
                'validModes' => implode('", "', MigrationCollectionLoader::VALID_VERSION_SELECTION_VALUES),
                'mode' => $mode,
            ]
        );
    }

    public static function implausibleCreationTimestamp(int $timestamp, MigrationStep $migration): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_IMPLAUSIBLE_CREATION_TIMESTAMP,
            'Migration timestamp must be between 1 and 2147483647 to ensure migration order is deterministic on every system, but "{{ timestamp }}" was given for "{{ migration }}".',
            [
                'timestamp' => $timestamp,
                'migration' => $migration::class,
            ]
        );
    }

    public static function migrationDirectoryNotCreated(string $directory): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED,
            'Migration directory "{{ directory }}" could not be created',
            [
                'directory' => $directory,
            ]
        );
    }

    /**
     * @param array<string> $pluginBundles
     */
    public static function moreThanOnePluginFound(string $pluginName, array $pluginBundles): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND,
            'More than one plugin name starting with "{{ pluginName }}" was found: {{ plugins }}',
            [
                'pluginName' => $pluginName,
                'plugins' => implode(';', $pluginBundles),
            ]
        );
    }

    public static function pluginNotFound(string $pluginName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FRAMEWORK_MIGRATION_PLUGIN_COULD_NOT_BE_FOUND,
            'Plugin "{{ pluginName }}" could not be found.',
            [
                'pluginName' => $pluginName,
            ]
        );
    }

    public static function unknownMigrationSource(string $name): self
    {
        return new UnknownMigrationSourceException($name);
    }
}
