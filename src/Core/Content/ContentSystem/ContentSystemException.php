<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @final
 */
#[Package('discovery')]
class ContentSystemException extends HttpException
{
    public const LAYOUT_ASSIGNMENT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND';
    public const LAYOUT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_NOT_FOUND';
    public const INVALID_MAP_KEY = 'CONTENT_SYSTEM__INVALID_MAP_KEY';
    public const INVALID_MAP_VALUE = 'CONTENT_SYSTEM__INVALID_MAP_VALUE';
    public const DATA_LOADER_NOT_REGISTERED = 'CONTENT_SYSTEM__DATA_LOADER_NOT_REGISTERED';
    public const CONFIG_SERIALIZER_NOT_REGISTERED = 'CONTENT_SYSTEM__CONFIG_SERIALIZER_NOT_REGISTERED';
    public const INVALID_FIELD_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_TYPE';
    public const INVALID_FIELD_VALUE_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE';
    public const CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED = 'CONTENT_SYSTEM__CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED';
    public const ELEMENT_NOT_FOUND = 'CONTENT_SYSTEM__ELEMENT_NOT_FOUND';
    public const PATH_INTEGRITY_VIOLATION = 'CONTENT_SYSTEM__PATH_INTEGRITY_VIOLATION';
    public const NO_FACTORY_CAN_HANDLE = 'CONTENT_SYSTEM__NO_FACTORY_CAN_HANDLE';
    public const INVALID_ENTITY_PATH = 'CONTENT_SYSTEM__INVALID_ENTITY_PATH';
    public const CONTEXT_PATH_NOT_RESOLVABLE = 'CONTENT_SYSTEM__CONTEXT_PATH_NOT_RESOLVABLE';
    public const REDISTRIBUTE_DOTTED_PATH = 'CONTENT_SYSTEM__REDISTRIBUTE_DOTTED_PATH';
    public const REDISTRIBUTE_CONFLICT = 'CONTENT_SYSTEM__REDISTRIBUTE_CONFLICT';
    public const CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE = 'CONTENT_SYSTEM__CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE';
    public const PROPERTY_ALIAS_WITH_DOT_NOTATION = 'CONTENT_SYSTEM__PROPERTY_ALIAS_WITH_DOT_NOTATION';
    public const PROPERTY_ALIAS_COLLISION = 'CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION';

    public static function dataLoaderNotRegistered(string $requirementType, string $elementType, string $elementId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DATA_LOADER_NOT_REGISTERED,
            'Data loader for requirement type "{{ requirementType }}" not registered. Element type: "{{ elementType }}", element ID: "{{ elementId }}"',
            ['requirementType' => $requirementType, 'elementType' => $elementType, 'elementId' => $elementId]
        );
    }

    public static function configSerializerNotRegistered(string $source): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONFIG_SERIALIZER_NOT_REGISTERED,
            'Config serializer for source "{{ source }}" is not registered',
            ['source' => $source]
        );
    }

    public static function invalidFieldType(string $expectedClass, string $actualClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_TYPE,
            'Expected field of type {{ expectedClass }}, got {{ actualClass }}',
            ['expectedClass' => $expectedClass, 'actualClass' => $actualClass]
        );
    }

    public static function invalidFieldValueType(string $fieldName, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_FIELD_VALUE_TYPE,
            'Field {{ fieldName }} expected {{ expectedType }}, got {{ actualType }}',
            ['fieldName' => $fieldName, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function invalidMapKey(string $mapType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MAP_KEY,
            '{{ mapType }} key must be string, got {{ actualType }}',
            ['mapType' => $mapType, 'actualType' => $actualType]
        );
    }

    public static function invalidMapValue(string $mapType, string $key, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_MAP_VALUE,
            '{{ mapType }} value for "{{ key }}" must be {{ expectedType }}, got {{ actualType }}',
            ['mapType' => $mapType, 'key' => $key, 'expectedType' => $expectedType, 'actualType' => $actualType]
        );
    }

    public static function layoutAssignmentNotFound(string $entityType, string $entityId, string $salesChannelId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LAYOUT_ASSIGNMENT_NOT_FOUND,
            'No layout assignment found for {{ entityType }} "{{ entityId }}" in sales channel "{{ salesChannelId }}"',
            ['entityType' => $entityType, 'entityId' => $entityId, 'salesChannelId' => $salesChannelId]
        );
    }

    public static function layoutNotFound(string $layoutId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LAYOUT_NOT_FOUND,
            'Content layout with ID "{{ layoutId }}" does not exist. This indicates a configuration error.',
            ['layoutId' => $layoutId]
        );
    }

    public static function elementNotFound(string $elementId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ELEMENT_NOT_FOUND,
            'Element with ID "{{ elementId }}" not found in layout',
            ['elementId' => $elementId]
        );
    }

    public static function pathIntegrityViolation(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PATH_INTEGRITY_VIOLATION,
            'Path integrity violation: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function criteriaFilterFieldDecodeNotSupported(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED,
            'CriteriaFilterField does not support decode. Use ResolutionConfigField for full encode/decode support with entity context.'
        );
    }

    public static function noFactoryCanHandle(string $path): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NO_FACTORY_CAN_HANDLE,
            'No context factory can handle the request for path "{{ path }}"',
            ['path' => $path]
        );
    }

    public static function invalidEntityPath(string $entityType, string $path, string $expectedFormat): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ENTITY_PATH,
            'Invalid {{ entityType }} path format: "{{ path }}". Expected format: {{ expectedFormat }}',
            [
                'entityType' => $entityType,
                'path' => $path,
                'expectedFormat' => $expectedFormat,
            ]
        );
    }

    public static function redistributeWithDottedPath(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDISTRIBUTE_DOTTED_PATH,
            'Context key "{{ key }}" uses dot notation and cannot be redistributed. Only base keys support redistribution.',
            ['key' => $contextKey]
        );
    }

    public static function redistributeConflict(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REDISTRIBUTE_CONFLICT,
            'Context key "{{ key }}" has both redistribute:true and explicit provides_context. Use one or the other.',
            ['key' => $contextKey]
        );
    }

    public static function consumerAliasWithoutRedistribute(string $contextKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE,
            'Context key "{{ key }}" has consumer_alias but redistribute is not true. consumer_alias requires redistribute:true.',
            ['key' => $contextKey]
        );
    }

    public static function contextPathNotResolvable(string $fullPath, string $elementId, ?string $reason = null): self
    {
        $message = 'Cannot resolve context path "{{ fullPath }}" for element "{{ elementId }}"';
        if ($reason !== null) {
            $message .= ': {{ reason }}';
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONTEXT_PATH_NOT_RESOLVABLE,
            $message,
            ['fullPath' => $fullPath, 'elementId' => $elementId, 'reason' => $reason]
        );
    }

    public static function propertyAliasWithDotNotation(string $contextKey, string $propertyAlias): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROPERTY_ALIAS_WITH_DOT_NOTATION,
            'Context key "{{ key }}" has property_alias "{{ alias }}" with dot notation. Property aliases must be simple property names without dots.',
            ['key' => $contextKey, 'alias' => $propertyAlias]
        );
    }

    public static function propertyAliasCollision(string $propertyKey, string $firstContext, string $secondContext): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROPERTY_ALIAS_COLLISION,
            'Property key "{{ propertyKey }}" is used by both context "{{ firstContext }}" and "{{ secondContext }}". Each property_alias must be unique within an element.',
            ['propertyKey' => $propertyKey, 'firstContext' => $firstContext, 'secondContext' => $secondContext]
        );
    }
}
