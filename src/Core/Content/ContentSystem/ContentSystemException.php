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
    public const CONTENT_NOT_FOUND = 'CONTENT_SYSTEM__CONTENT_NOT_FOUND';
    public const ENTITY_NOT_FOUND = 'CONTENT_SYSTEM__ENTITY_NOT_FOUND';
    public const LAYOUT_ASSIGNMENT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND';
    public const LAYOUT_ASSIGNMENT_NOT_FOUND_FOR_ROUTE = 'CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND_FOR_ROUTE';

    public const LAYOUT_NOT_FOUND = 'CONTENT_SYSTEM__LAYOUT_NOT_FOUND';
    public const RESOLUTION_FAILED = 'CONTENT_SYSTEM__RESOLUTION_FAILED';
    public const PAGE_BUILDING_FAILED = 'CONTENT_SYSTEM__PAGE_BUILDING_FAILED';
    public const HYDRATION_FAILED = 'CONTENT_SYSTEM__HYDRATION_FAILED';

    public const INVALID_MAP_KEY = 'CONTENT_SYSTEM__INVALID_MAP_KEY';
    public const INVALID_MAP_VALUE = 'CONTENT_SYSTEM__INVALID_MAP_VALUE';

    public const DATA_LOADER_NOT_REGISTERED = 'CONTENT_SYSTEM__DATA_LOADER_NOT_REGISTERED';
    public const CONFIG_SERIALIZER_NOT_REGISTERED = 'CONTENT_SYSTEM__CONFIG_SERIALIZER_NOT_REGISTERED';

    public const INVALID_FIELD_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_TYPE';
    public const INVALID_FIELD_VALUE_TYPE = 'CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE';
    public const CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED = 'CONTENT_SYSTEM__CRITERIA_FILTER_FIELD_DECODE_NOT_SUPPORTED';

    public const ELEMENT_NOT_FOUND = 'CONTENT_SYSTEM__ELEMENT_NOT_FOUND';
    public const INVALID_ELEMENT_ID = 'CONTENT_SYSTEM__INVALID_ELEMENT_ID';
    public const PATH_INTEGRITY_VIOLATION = 'CONTENT_SYSTEM__PATH_INTEGRITY_VIOLATION';

    public static function contentNotFound(string $pathInfo): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CONTENT_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'content', 'field' => 'path', 'value' => $pathInfo]
        );
    }

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

    public static function entityNotFound(string $entityType, string $identifier, string $matchField): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ENTITY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => $entityType, 'field' => $matchField, 'value' => $identifier]
        );
    }

    public static function parameterResolutionFailed(string $entityType, string $matchField, string $value, string $placeholder): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ENTITY_NOT_FOUND,
            'Could not resolve parameter "{{ placeholder }}" to {{ entity }} entity. No entity found where {{ field }} = "{{ value }}"',
            ['placeholder' => $placeholder, 'entity' => $entityType, 'field' => $matchField, 'value' => $value]
        );
    }

    public static function hydrationFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::HYDRATION_FAILED,
            'Entity hydration failed: {{ reason }}',
            ['reason' => $reason],
            $previous
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

    /**
     * @param list<string> $resolvedPlaceholders
     */
    public static function layoutAssignmentNotFoundForRoute(
        string $routeId,
        string $routePattern,
        array $resolvedPlaceholders,
        string $salesChannelId
    ): self {
        if (\count($resolvedPlaceholders) === 0) {
            return new self(
                Response::HTTP_NOT_FOUND,
                self::LAYOUT_ASSIGNMENT_NOT_FOUND_FOR_ROUTE,
                'No layout assignment for route "{{ routePattern }}" ({{ routeId }}) in sales channel {{ salesChannelId }}',
                [
                    'routePattern' => $routePattern,
                    'routeId' => $routeId,
                    'salesChannelId' => $salesChannelId,
                ]
            );
        }

        return new self(
            Response::HTTP_NOT_FOUND,
            self::LAYOUT_ASSIGNMENT_NOT_FOUND_FOR_ROUTE,
            'No layout assignment for route "{{ routePattern }}" ({{ routeId }}) with placeholders [{{ placeholders }}] in sales channel {{ salesChannelId }}',
            [
                'routePattern' => $routePattern,
                'routeId' => $routeId,
                'placeholders' => implode(', ', $resolvedPlaceholders),
                'salesChannelId' => $salesChannelId,
            ]
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

    public static function layoutRefineryFailed(string $layoutId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PAGE_BUILDING_FAILED,
            'Page building failed for layout "{{ layoutId }}": {{ reason }}',
            ['layoutId' => $layoutId, 'reason' => $reason],
            $previous
        );
    }

    public static function resolutionFailed(string $routeName, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::RESOLUTION_FAILED,
            'Entity resolution failed for route "{{ routeName }}": {{ reason }}',
            ['routeName' => $routeName, 'reason' => $reason],
            $previous
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

    public static function invalidElementId(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ELEMENT_ID,
            'The elementId parameter must be a non-empty string'
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
}
