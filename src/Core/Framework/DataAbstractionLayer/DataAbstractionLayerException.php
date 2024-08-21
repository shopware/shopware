<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidRangeFilterParamException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingSystemTranslationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingTranslationLanguageException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\Feature;
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
    public const INVALID_LANGUAGE_ID = 'FRAMEWORK__INVALID_LANGUAGE_ID';
    public const VERSION_NO_COMMITS_FOUND = 'FRAMEWORK__VERSION_NO_COMMITS_FOUND';
    public const VERSION_NOT_EXISTS = 'FRAMEWORK__VERSION_NOT_EXISTS';
    public const MIGRATION_STUB_NOT_FOUND = 'FRAMEWORK__MIGRATION_STUB_NOT_FOUND';
    public const MIGRATION_DIRECTORY_NOT_FOUND = 'FRAMEWORK__MIGRATION_DIRECTORY_NOT_FOUND';
    public const DATABASE_PLATFORM_INVALID = 'FRAMEWORK__DATABASE_PLATFORM_INVALID';
    public const FIELD_TYPE_NOT_FOUND = 'FRAMEWORK__FIELD_TYPE_NOT_FOUND';
    public const PLUGIN_NOT_FOUND = 'FRAMEWORK__PLUGIN_NOT_FOUND';
    public const INVALID_FILTER_QUERY = 'FRAMEWORK__INVALID_FILTER_QUERY';
    public const INVALID_RANGE_FILTER_PARAMS = 'FRAMEWORK__INVALID_RANGE_FILTER_PARAMS';
    public const INVALID_SORT_QUERY = 'FRAMEWORK__INVALID_SORT_QUERY';
    public const UNABLE_TO_FETCH_FOREIGN_KEY = 'FRAMEWORK__UNABLE_TO_FETCH_FOREIGN_KEY';
    public const REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND = 'FRAMEWORK__REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND';
    public const INCONSISTENT_PRIMARY_KEY = 'FRAMEWORK__INCONSISTENT_PRIMARY_KEY';
    public const FIELD_BY_STORAGE_NAME_NOT_FOUND = 'FRAMEWORK__FIELD_BY_STORAGE_NAME_NOT_FOUND';
    public const MISSING_PARENT_FOREIGN_KEY = 'FRAMEWORK__MISSING_PARENT_FOREIGN_KEY';
    public const INVALID_WRITE_INPUT = 'FRAMEWORK__INVALID_WRITE_INPUT';
    public const DECODE_HANDLED_BY_HYDRATOR = 'FRAMEWORK__DECODE_HANDLED_BY_HYDRATOR';
    public const ATTRIBUTE_NOT_FOUND = 'FRAMEWORK__ATTRIBUTE_NOT_FOUND';
    public const EXPECTED_ARRAY_WITH_TYPE = 'FRAMEWORK__EXPECTED_ARRAY_WITH_TYPE';
    public const INVALID_AGGREGATION_NAME = 'FRAMEWORK__INVALID_AGGREGATION_NAME';

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
        return new InvalidFilterQueryException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FILTER_QUERY,
            $message,
            ['path' => $path]
        );
    }

    public static function invalidRangeFilterParams(string $message): self
    {
        return new InvalidRangeFilterParamException(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_RANGE_FILTER_PARAMS,
            $message,
        );
    }

    public static function invalidSortQuery(string $message, string $path = ''): self
    {
        return new InvalidSortQueryException(
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

    /**
     * @param array<string> $primaryKey
     */
    public static function unableToFetchForeignKey(string $entity, array $primaryKey): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNABLE_TO_FETCH_FOREIGN_KEY,
            'Unable to fetch foreign key for {{ entity }} with primary key {{ primaryKey }}',
            ['entity' => $entity, 'primaryKey' => implode(', ', $primaryKey)]
        );
    }

    public static function missingParentForeignKey(string $entity): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_PARENT_FOREIGN_KEY,
            \sprintf('Can not detect foreign key for parent definition %s', $entity)
        );
    }

    public static function fieldByStorageNameNotFound(string $entity, string $storageName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FIELD_BY_STORAGE_NAME_NOT_FOUND,
            \sprintf('Field by storage name %s not found in entity %s', $storageName, $entity)
        );
    }

    public static function inconsistentPrimaryKey(string $entity, string $primaryKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INCONSISTENT_PRIMARY_KEY,
            \sprintf('Inconsistent primary key %s for entity %s', $primaryKey, $entity)
        );
    }

    public static function referenceFieldByStorageNameNotFound(string $entity, string $storageName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND,
            \sprintf('Can not detect reference field with storage name %s in definition %s', $storageName, $entity)
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     *
     * @param class-string $definitionClass
     */
    public static function fkFieldByStorageNameNotFound(string $definitionClass, string $storageName): self|\RuntimeException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \RuntimeException(
                \sprintf(
                    'Could not find FK field "%s" from definition "%s"',
                    $storageName,
                    $definitionClass,
                )
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND,
            \sprintf('Can not detect FK field with storage name %s in definition %s', $storageName, $definitionClass)
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     *
     * @param class-string $definitionClass
     */
    public static function languageFieldByStorageNameNotFound(string $definitionClass, string $storageName): self|\RuntimeException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \RuntimeException(
                \sprintf(
                    'Could not find language field "%s" in definition "%s"',
                    $storageName,
                    $definitionClass
                )
            );
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND,
            \sprintf('Can not detect language field with storage name %s in definition %s', $storageName, $definitionClass)
        );
    }

    public static function invalidWriteInput(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_WRITE_INPUT,
            $message,
        );
    }

    public static function expectedArray(string $path): self
    {
        return new ExpectedArrayException($path);
    }

    public static function expectedAssociativeArray(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_WRITE_INPUT,
            'Expected data at {{ path }} to be an associative array.',
            ['path' => $path]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function decodeHandledByHydrator(Field $field): self|DecodeByHydratorException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new DecodeByHydratorException($field);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DECODE_HANDLED_BY_HYDRATOR,
            'Decoding of {{ fieldClass }} is handled by the entity hydrator.',
            ['fieldClass' => $field::class]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:remove-exception - Use self::referenceFieldByStorageNameNotFound instead
     *
     * @param class-string $definitionClass
     */
    public static function definitionFieldDoesNotExist(string $definitionClass, string $field): self|\RuntimeException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \RuntimeException(\sprintf(
                'Could not find reference field "%s" from definition "%s"',
                $field,
                $definitionClass
            ));
        }

        return self::referenceFieldByStorageNameNotFound($definitionClass, $field);
    }

    public static function missingSystemTranslation(string $path): self
    {
        return new MissingSystemTranslationException($path);
    }

    public static function missingTranslation(string $path, int $index): self
    {
        return new MissingTranslationLanguageException($path, $index);
    }

    public static function canNotFindAttribute(string $attribute, string $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ATTRIBUTE_NOT_FOUND,
            'Can not find attribute "{{ attribute }}" for property {{ property }}',
            ['attribute' => $attribute, 'property' => $property]
        );
    }

    public static function expectedArrayWithType(string $path, string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::EXPECTED_ARRAY_WITH_TYPE,
            \sprintf('Expected data at %s to be of the type array, %s given', $path, $type),
            ['path' => $path, 'type' => $type]
        );
    }

    public static function invalidAggregationName(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_AGGREGATION_NAME,
            'Invalid aggregation name "{{ name }}", cannot contain question marks und colon.',
            ['name' => $name]
        );
    }

    public static function invalidIdFieldType(Field $field, mixed $value): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FIELD_SERIALIZER_CODE,
            \sprintf(
                'Expected ID field value to be of type "string", but got "%s" in field "%s".',
                \gettype($value),
                $field->getPropertyName()
            )
        );
    }

    public static function invalidArraySerialization(Field $field, mixed $value): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_WRITE_INPUT,
            \sprintf(
                'Expected a string but got an array or invalid type in field "%s". Value: "%s".',
                $field->getPropertyName(),
                print_r($value, true)
            )
        );
    }
}
