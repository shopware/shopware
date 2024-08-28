<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\ParentAssociationCanNotBeFetched;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\CanNotFindParentStorageFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DecodeByHydratorException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityRepositoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InternalFieldAccessNotAllowedException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidParentAssociationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidRangeFilterParamException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingSystemTranslationException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\MissingTranslationLanguageException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldForeignKeyConstraintMissingException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ParentFieldNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PrimaryKeyNotProvidedException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
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
    public const MISSING_FIELD_VALUE = 'FRAMEWORK__MISSING_FIELD_VALUE';
    public const NOT_CUSTOM_FIELDS_SUPPORT = 'FRAMEWORK__NOT_CUSTOM_FIELDS_SUPPORT';
    public const INTERNAL_FIELD_ACCESS_NOT_ALLOWED = 'FRAMEWORK__INTERNAL_FIELD_ACCESS_NOT_ALLOWED';
    public const PROPERTY_NOT_FOUND = 'FRAMEWORK__PROPERTY_NOT_FOUND';
    public const NOT_AN_INSTANCE_OF_ENTITY_COLLECTION = 'FRAMEWORK__NOT_AN_INSTANCE_OF_ENTITY_COLLECTION';
    public const REFERENCE_FIELD_NOT_FOUND = 'FRAMEWORK__REFERENCE_FIELD_NOT_FOUND';
    public const NO_ID_FOR_ASSOCIATION = 'FRAMEWORK__NO_ID_FOR_ASSOCIATION';
    public const NO_INVERSE_ASSOCIATION_FOUND = 'FRAMEWORK__NO_INVERSE_ASSOCIATION_FOUND';
    public const NOT_SUPPORTED_FIELD_FOR_AGGREGATION = 'FRAMEWORK__NOT_SUPPORTED_FIELD_FOR_AGGREGATION';
    public const INVALID_DATE_FORMAT = 'FRAMEWORK__INVALID_DATE_FORMAT';
    public const INVALID_DATE_HISTOGRAM_INTERVAL = 'FRAMEWORK__INVALID_DATE_HISTOGRAM_INTERVAL';
    public const INVALID_TIMEZONE = 'FRAMEWORK__INVALID_TIMEZONE';
    public const CANNOT_FIND_PARENT_STORAGE_FIELD = 'FRAMEWORK__CAN_NOT_FIND_PARENT_STORAGE_FIELD';
    public const INVALID_PARENT_ASSOCIATION_EXCEPTION = 'FRAMEWORK__INVALID_PARENT_ASSOCIATION_EXCEPTION';
    public const PARENT_FIELD_KEY_CONSTRAINT_MISSING = 'FRAMEWORK__PARENT_FIELD_KEY_CONSTRAINT_MISSING';
    public const PARENT_FIELD_NOT_FOUND_EXCEPTION = 'FRAMEWORK__PARENT_FIELD_NOT_FOUND_EXCEPTION';
    public const PRIMARY_KEY_NOT_PROVIDED = 'FRAMEWORK__PRIMARY_KEY_NOT_PROVIDED';

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
     * @param list<string> $allowedFormats
     */
    public static function invalidDateFormat(string $dateFormat, array $allowedFormats): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_DATE_FORMAT,
            'Provided date format "{{ dateFormat }}" is not supported. Supported formats: {{ allowedFormats }}.',
            ['dateFormat' => $dateFormat, 'allowedFormats' => implode(', ', $allowedFormats)]
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
            'Can not detect foreign key for parent definition {{ entity }}',
            ['entity' => $entity]
        );
    }

    public static function fieldByStorageNameNotFound(string $entity, string $storageName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FIELD_BY_STORAGE_NAME_NOT_FOUND,
            'Field by storage name {{ storageName }} not found in entity {{ entity }}',
            ['storageName' => $storageName, 'entity' => $entity]
        );
    }

    public static function inconsistentPrimaryKey(string $entity, string $primaryKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INCONSISTENT_PRIMARY_KEY,
            'Inconsistent primary key {{ primaryKey }} for entity {{ entity }}',
            ['primaryKey' => $primaryKey, 'entity' => $entity]
        );
    }

    public static function referenceFieldByStorageNameNotFound(string $entity, string $storageName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND,
            'Can not detect reference field with storage name {{ storageName }} in definition {{ entity }}',
            ['storageName' => $storageName, 'entity' => $entity]
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
            'Can not detect FK field with storage name {{ storageName }} in definition {{ definitionClass }}',
            ['storageName' => $storageName, 'definitionClass' => $definitionClass]
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
            'Can not detect language field with storage name {{ storageName }} in definition {{ definitionClass }}',
            ['storageName' => $storageName, 'definitionClass' => $definitionClass]
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
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
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

    public static function missingFieldValue(Field $field): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_FIELD_VALUE,
            'A value for the field "{{ field }}" is required, but it is missing or `null`.',
            ['field' => $field->getPropertyName()]
        );
    }

    public static function notCustomFieldsSupport(string $methodName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NOT_CUSTOM_FIELDS_SUPPORT,
            '{{ methodName }}() is only supported for entities that use the EntityCustomFieldsTrait',
            ['methodName' => $methodName]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     * @deprecated tag:v6.7.0 - Parameter `entity` will be removed
     */
    public static function internalFieldAccessNotAllowed(string $property, string $entityClassName, object $entity): self|InternalFieldAccessNotAllowedException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InternalFieldAccessNotAllowedException($property, $entity);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INTERNAL_FIELD_ACCESS_NOT_ALLOWED,
            'Access to property "{{ property }}" not allowed on entity "{{ entityClassName }}".',
            ['property' => $property, 'entityClassName' => $entityClassName]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function propertyNotFound(string $property, string $entityClassName): self|\InvalidArgumentException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new \InvalidArgumentException(\sprintf('Property %s do not exist in class %s', $property, $entityClassName));
        }

        return new PropertyNotFoundException($property, $entityClassName);
    }

    public static function unsupportedCommandType(WriteCommand $command): HttpException
    {
        return new UnsupportedCommandTypeException($command);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function parentFieldNotFound(EntityDefinition $definition): self|ParentFieldNotFoundException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new ParentFieldNotFoundException($definition);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PARENT_FIELD_NOT_FOUND_EXCEPTION,
            'Can not find parent property \'parent\' field for definition {{ definition }',
            ['definition' => $definition->getEntityName()]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function invalidParentAssociation(EntityDefinition $definition, Field $parentField): self|InvalidParentAssociationException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidParentAssociationException($definition, $parentField);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_PARENT_ASSOCIATION_EXCEPTION,
            'Parent property for {{ definition }} expected to be an ManyToOneAssociationField got {{ fieldDefinition }}',
            ['definition' => $definition->getEntityName(), 'fieldDefinition' => $parentField::class]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function cannotFindParentStorageField(EntityDefinition $definition): self|CanNotFindParentStorageFieldException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new CanNotFindParentStorageFieldException($definition);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_FIND_PARENT_STORAGE_FIELD,
            'Can not find FkField for parent property definition {{ definition }}',
            ['definition' => $definition->getEntityName()]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function parentFieldForeignKeyConstraintMissing(EntityDefinition $definition, Field $parentField): self|ParentFieldForeignKeyConstraintMissingException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new ParentFieldForeignKeyConstraintMissingException($definition, $parentField);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PARENT_FIELD_KEY_CONSTRAINT_MISSING,
            'Foreign key property {{ propertyName }} of parent association in definition {{ definition }} expected to be an FkField got %s',
            [
                'definition' => $definition->getEntityName(),
                'propertyName' => $parentField->getPropertyName(),
                'propertyClass' => $parentField::class,
            ]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function primaryKeyNotProvided(EntityDefinition $definition, Field $field): self|PrimaryKeyNotProvidedException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new PrimaryKeyNotProvidedException($definition, $field);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRIMARY_KEY_NOT_PROVIDED,
            'Expected primary key field {{ propertyName }} for definition {{ definition }} not provided',
            ['definition' => $definition->getEntityName(), 'propertyName' => $field->getPropertyName()]
        );
    }

    public static function notAnInstanceOfEntityCollection(string $collectionClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NOT_AN_INSTANCE_OF_ENTITY_COLLECTION,
            'Collection class "{{ collectionClass }}" has to be an instance of EntityCollection',
            ['collectionClass' => $collectionClass]
        );
    }

    public static function referenceFieldNotFound(string $referenceField, string $referenceEntity, string $entity): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REFERENCE_FIELD_NOT_FOUND,
            'Reference field "{{ referenceField }}" not found in entity definition "{{ referenceEntity }}" for entity "{{ entity }}"',
            ['referenceField' => $referenceField, 'referenceEntity' => $referenceEntity, 'entity' => $entity]
        );
    }

    public static function noIdForAssociation(string $entityName, string $propertyName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_ID_FOR_ASSOCIATION,
            'Paginated to-many associations must have an ID field. No ID field found for association {{ entityName }}.{{ propertyName }}',
            ['entityName' => $entityName, 'propertyName' => $propertyName]
        );
    }

    public static function noInverseAssociationFound(string $propertyName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_INVERSE_ASSOCIATION_FOUND,
            'No inverse many-to-many association found for association {{ propertyName }}',
            ['propertyName' => $propertyName]
        );
    }

    public static function parentAssociationCannotBeFetched(): self
    {
        return new ParentAssociationCanNotBeFetched();
    }

    public static function invalidAggregationQuery(string $message): self
    {
        return new InvalidAggregationQueryException($message);
    }

    /**
     * @param list<class-string<Field>> $supportedFields
     */
    public static function notSupportedFieldForAggregation(string $aggregationType, string $field, string $fieldClass, array $supportedFields): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NOT_SUPPORTED_FIELD_FOR_AGGREGATION,
            'Provided field "{{ field }}" of type "{{ fieldClass }}" is not supported in "{{ aggregationType }}" (supported fields: {{ supportedFields }})',
            ['aggregationType' => $aggregationType, 'field' => $field, 'fieldClass' => $fieldClass, 'supportedFields' => implode(', ', $supportedFields)]
        );
    }

    /**
     * @param list<DateHistogramAggregation::PER_*> $allowedIntervals
     */
    public static function invalidDateHistogramInterval(string $interval, array $allowedIntervals): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_DATE_HISTOGRAM_INTERVAL,
            'Provided date histogram interval "{{ interval }}" is not supported. Supported intervals: {{ allowedIntervals }}.',
            ['interval' => $interval, 'allowedIntervals' => implode(', ', $allowedIntervals)]
        );
    }

    public static function invalidTimeZone(string $timeZone): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_TIMEZONE,
            'Given "{{ timeZone }}" is not a valid timezone',
            ['timeZone' => $timeZone]
        );
    }

    public static function invalidWriteConstraintViolation(\Symfony\Component\Validator\ConstraintViolationList $violationList, string $getPath): WriteConstraintViolationException
    {
        return new WriteConstraintViolationException($violationList, $getPath);
    }

    public static function definitionNotFound(string $entity): DefinitionNotFoundException
    {
        return new DefinitionNotFoundException($entity);
    }

    public static function entityRepositoryNotFound(string $entity): EntityRepositoryNotFoundException
    {
        return new EntityRepositoryNotFoundException($entity);
    }
}
