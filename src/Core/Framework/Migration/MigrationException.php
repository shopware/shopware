<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Exception\UnknownMigrationSourceException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class MigrationException extends HttpException
{
    final public const FRAMEWORK_MIGRATION_INVALID_VERSION_SELECTION_MODE = 'FRAMEWORK__MIGRATION_INVALID_VERSION_SELECTION_MODE';
    final public const FRAMEWORK_MIGRATION_INVALID_MIGRATION_SOURCE = 'FRAMEWORK__INVALID_MIGRATION_SOURCE';
    final public const FRAMEWORK_MIGRATION_IMPLAUSIBLE_CREATION_TIMESTAMP = 'FRAMEWORK__MIGRATION_IMPLAUSIBLE_CREATION_TIMESTAMP';
    final public const MIGRATION_COULD_NOT_DETERMINE_TIMESTAMP = 'FRAMEWORK__MIGRATION_COULD_NOT_DETERMINE_TIMESTAMP';
    final public const FRAMEWORK_MIGRATION_PLUGIN_COULD_NOT_BE_FOUND = 'FRAMEWORK__MIGRATION_PLUGIN_COULD_NOT_BE_FOUND';
    final public const FRAMEWORK_MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND = 'FRAMEWORK__MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND';
    final public const FRAMEWORK_MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED = 'FRAMEWORK__MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED';
    final public const INVALID_ARGUMENT = 'FRAMEWORK__MIGRATION_INVALID_ARGUMENT_EXCEPTION';
    final public const MIGRATION_ERROR = 'FRAMEWORK__MIGRATION_ERROR';
    final public const INVALID_MIGRATION = 'FRAMEWORK__INVALID_MIGRATION';
    final public const MIGRATION_MULTI_COLUMN_PRIMARY_KEY = 'FRAMEWORK__MIGRATION_MULTI_COLUMN_PRIMARY_KEY';
    final public const MIGRATION_NO_PRIMARY_KEY = 'FRAMEWORK__MIGRATION_NO_COLUMN_PRIMARY_KEY';
    final public const LOGIC_ERROR = 'FRAMEWORK__LOGIC_ERROR';
    final public const MIGRATION_FILE_DOES_NOT_EXIST = 'FRAMEWORK__MIGRATION_FILE_DOES_NOT_EXIST';

    private const REVERSIBLE_MIGRATION_NOT_INSTANTIABLE = 'FRAMEWORK__REVERSIBLE_MIGRATION_NOT_INSTANTIABLE';
    private const REVERSIBLE_MIGRATION_INVALID_TIMESTAMP = 'FRAMEWORK__REVERSIBLE_MIGRATION_INVALID_TIMESTAMP';
    private const REVERSIBLE_MIGRATION_DUPLICATE_TIMESTAMP = 'FRAMEWORK__REVERSIBLE_MIGRATION_DUPLICATE_TIMESTAMP';
    private const REVERSIBLE_MIGRATION_OUT_OF_ORDER = 'FRAMEWORK__REVERSIBLE_MIGRATION_OUT_OF_ORDER';
    private const REVERSIBLE_MIGRATION_TIMESTAMP_CHANGED = 'FRAMEWORK__REVERSIBLE_MIGRATION_TIMESTAMP_CHANGED';
    private const REVERSIBLE_MIGRATION_MISSING_APPLIED = 'FRAMEWORK__REVERSIBLE_MIGRATION_MISSING_APPLIED';
    private const REVERSIBLE_MIGRATION_LOCK_NOT_ACQUIRED = 'FRAMEWORK__REVERSIBLE_MIGRATION_LOCK_NOT_ACQUIRED';
    private const REVERSIBLE_MIGRATION_INVALID_STATE = 'FRAMEWORK__REVERSIBLE_MIGRATION_INVALID_STATE';
    private const MIGRATION_DIRECTORY_NOT_READABLE = 'FRAMEWORK__MIGRATION_DIRECTORY_NOT_READABLE';

    public static function invalidArgument(string $message): self
    {
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

    public static function couldNotDetermineTimestamp(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_COULD_NOT_DETERMINE_TIMESTAMP,
            'Could not determine current timestamp.'
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

    public static function migrationError(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_ERROR,
            'Migration error: {{ errorMessage }}',
            ['errorMessage' => $message],
            $previous
        );
    }

    public static function invalidMigrationClass(string $class, string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MIGRATION,
            'Unable to load migration {{ class }} at path {{ path }}',
            ['class' => $class, 'path' => $path],
        );
    }

    public static function multiColumnPrimaryKey(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_MULTI_COLUMN_PRIMARY_KEY,
            'Tables with multi column primary keys are not supported. Maybe this migration did already run.'
        );
    }

    public static function noPrimaryKey(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_NO_PRIMARY_KEY,
            'Tables with no primary key are not supported.'
        );
    }

    public static function logicError(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIC_ERROR,
            $message
        );
    }

    public static function migrationFileDoesNotExist(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_FILE_DOES_NOT_EXIST,
            'The provided migration file does not exist: "{{ path }}"',
            ['path' => $path]
        );
    }

    public static function migrationDirectoryNotReadable(string $directory): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_DIRECTORY_NOT_READABLE,
            'Migration directory "{{ directory }}" could not be read.',
            ['directory' => $directory]
        );
    }

    public static function reversibleMigrationNotInstantiable(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_NOT_INSTANTIABLE,
            'Reversible migration "{{ class }}" must be instantiable without constructor arguments.',
            ['class' => $class]
        );
    }

    public static function reversibleMigrationInvalidTimestamp(string $class, int $timestamp): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_INVALID_TIMESTAMP,
            'Migration timestamp must be between 1 and 2147483647 to ensure migration order is deterministic on every system, but "{{ timestamp }}" was given for "{{ class }}".',
            ['class' => $class, 'timestamp' => $timestamp]
        );
    }

    public static function duplicateMigrationTimestamp(string $plugin, int $timestamp, string $first, string $second): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_DUPLICATE_TIMESTAMP,
            'Plugin "{{ plugin }}" contains two reversible migrations with timestamp {{ timestamp }}: "{{ first }}" and "{{ second }}".',
            ['plugin' => $plugin, 'timestamp' => $timestamp, 'first' => $first, 'second' => $second]
        );
    }

    public static function migrationOutOfOrder(string $plugin, string $class, int $timestamp, int $latestApplied): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_OUT_OF_ORDER,
            'Reversible migration "{{ class }}" ({{ timestamp }}) for plugin "{{ plugin }}" is older than the latest applied migration ({{ latestApplied }}). Add new migrations with a later timestamp.',
            ['plugin' => $plugin, 'class' => $class, 'timestamp' => $timestamp, 'latestApplied' => $latestApplied]
        );
    }

    public static function migrationTimestampChanged(string $class, int $recorded, int $declared): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_TIMESTAMP_CHANGED,
            'Applied reversible migration "{{ class }}" changed its creation timestamp from {{ recorded }} to {{ declared }}. Restore the recorded timestamp.',
            ['class' => $class, 'recorded' => $recorded, 'declared' => $declared]
        );
    }

    public static function missingAppliedMigration(string $plugin, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_MISSING_APPLIED,
            'Applied reversible migration "{{ class }}" for plugin "{{ plugin }}" is no longer available. Restore the migration class before continuing.',
            ['plugin' => $plugin, 'class' => $class]
        );
    }

    public static function migrationLockNotAcquired(string $plugin): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_LOCK_NOT_ACQUIRED,
            'Could not acquire the reversible migration lock for plugin "{{ plugin }}".',
            ['plugin' => $plugin]
        );
    }

    public static function invalidMigrationState(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REVERSIBLE_MIGRATION_INVALID_STATE,
            'The reversible migration state contains an invalid row.'
        );
    }
}
