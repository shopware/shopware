<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class DataAbstractionLayerException extends HttpException
{
    public const INVALID_FIELD_SERIALIZER_CODE = 'FRAMEWORK__INVALID_FIELD_SERIALIZER';

    public const INVALID_CRON_INTERVAL_CODE = 'FRAMEWORK__INVALID_CRON_INTERVAL_FORMAT';

    public const INVALID_DATE_INTERVAL_CODE = 'FRAMEWORK__INVALID_DATE_INTERVAL_FORMAT';

    public const INVALID_CRITERIA_IDS = 'FRAMEWORK__INVALID_CRITERIA_IDS';

    public const INVALID_API_CRITERIA_IDS = 'FRAMEWORK__INVALID_API_CRITERIA_IDS';

    public const CANNOT_CREATE_NEW_VERSION = 'FRAMEWORK__CANNOT_CREATE_NEW_VERSION';

    public const VERSION_MERGE_ALREADY_LOCKED = 'FRAMEWORK__VERSION_MERGE_ALREADY_LOCKED';

    final public const INVALID_LANGUAGE_ID = 'FRAMEWORK__INVALID_LANGUAGE_ID';
    public const VERSION_NO_COMMITS_FOUND = 'FRAMEWORK__VERSION_NO_COMMITS_FOUND';
    public const VERSION_NOT_EXISTS = 'FRAMEWORK__VERSION_NOT_EXISTS';
    public const MIGRATION_STUB_NOT_FOUND = 'FRAMEWORK__MIGRATION_STUB_NOT_FOUND';
    public const MIGRATION_DIRECTORY_NOT_FOUND = 'FRAMEWORK__MIGRATION_DIRECTORY_NOT_FOUND';
    public const DATABASE_PLATFORM_INVALID = 'FRAMEWORK__DATABASE_PLATFORM_INVALID';
    public const FIELD_TYPE_NOT_FOUND = 'FRAMEWORK__FIELD_TYPE_NOT_FOUND';
    public const PLUGIN_NOT_FOUND = 'FRAMEWORK__PLUGIN_NOT_FOUND';
    public const INVALID_FILTER_QUERY = 'FRAMEWORK__INVALID_FILTER_QUERY';

    public static function invalidSerializerField(string $expectedClass, Field $field): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FIELD_SERIALIZER_CODE,
            'Expected field of type "{{ expectedField }}" got "{{ field }}".',
            ['expectedField' => $expectedClass, 'field' => $field::class]
        );
    }

    public static function invalidCronIntervalFormat(string $cronIntervalString): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CRON_INTERVAL_CODE,
            'Unknown or bad CronInterval format "{{ cronIntervalString }}".',
            ['cronIntervalString' => $cronIntervalString],
        );
    }

    public static function invalidDateIntervalFormat(
        string $dateIntervalString,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DATE_INTERVAL_CODE,
            'Unknown or bad DateInterval format "{{ dateIntervalString }}".',
            ['dateIntervalString' => $dateIntervalString],
            $previous,
        );
    }

    /**
     * @param array<mixed> $ids
     */
    public static function invalidCriteriaIds(array $ids, string $reason): self
    {
        return new InvalidCriteriaIdsException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_CRITERIA_IDS,
            'Invalid ids provided in criteria. {{ reason }}. Ids: {{ ids }}.',
            ['ids' => print_r($ids, true), 'reason' => $reason]
        );
    }

    public static function invalidApiCriteriaIds(self $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_API_CRITERIA_IDS,
            $previous->getMessage(),
            $previous->getParameters(),
        );
    }

    public static function invalidLanguageId(?string $languageId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_LANGUAGE_ID,
            'The provided language id "{{ languageId }}" is invalid.',
            ['languageId' => $languageId]
        );
    }

    public static function invalidFilterQuery(string $message, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FILTER_QUERY,
            $message,
            ['path' => $path]
        );
    }

    public static function cannotCreateNewVersion(string $entity, string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CANNOT_CREATE_NEW_VERSION,
            'Cannot create new version. {{ entity }} by id {{ id }} not found.',
            ['entity' => $entity, 'id' => $id]
        );
    }

    public static function versionMergeAlreadyLocked(string $versionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VERSION_MERGE_ALREADY_LOCKED,
            'Merging of version {{ versionId }} is locked, as the merge is already running by another process.',
            ['versionId' => $versionId]
        );
    }

    public static function noCommitsFound(string $versionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VERSION_NO_COMMITS_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'commits', 'field' => 'version', 'value' => $versionId]
        );
    }

    public static function versionNotExists(string $versionId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VERSION_NOT_EXISTS,
            'Version {{ versionId }} does not exist. Version was probably deleted or already merged.',
            ['versionId' => $versionId]
        );
    }

    public static function migrationStubNotFound(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_STUB_NOT_FOUND,
            'Unable to load stub file from: {{ path }}.',
            ['path' => $path]
        );
    }

    public static function migrationDirectoryNotFound(string $path): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MIGRATION_DIRECTORY_NOT_FOUND,
            'Migration directory not found: {{ path }}.',
            ['path' => $path]
        );
    }

    public static function databasePlatformInvalid(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATABASE_PLATFORM_INVALID,
            'Database platform can not be detected'
        );
    }

    public static function fieldHasNoType(string $fieldName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FIELD_TYPE_NOT_FOUND,
            'Field {{ fieldName }} has no type',
            ['fieldName' => $fieldName]
        );
    }

    public static function pluginNotFound(string $pluginName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PLUGIN_NOT_FOUND,
            'Plugin {{ fieldName }} not be found',
            ['pluginName' => $pluginName]
        );
    }
}
