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
    public const UNSUPPORTED_ENTITY_TYPE = 'CONTENT_SYSTEM__UNSUPPORTED_ENTITY_TYPE';
    public const UNSUPPORTED_FACTORY_TYPE = 'CONTENT_SYSTEM__UNSUPPORTED_FACTORY_TYPE';
    public const INVALID_REQUEST_PARAMETER = 'CONTENT_SYSTEM__INVALID_REQUEST_PARAMETER';
    public const NO_FACTORY_CAN_HANDLE = 'CONTENT_SYSTEM__NO_FACTORY_CAN_HANDLE';
    public const INVALID_PRODUCT_PATH = 'CONTENT_SYSTEM__INVALID_PRODUCT_PATH';
    public const INVALID_CATEGORY_PATH = 'CONTENT_SYSTEM__INVALID_CATEGORY_PATH';
    public const INVALID_LANDING_PAGE_PATH = 'CONTENT_SYSTEM__INVALID_LANDING_PAGE_PATH';
    public const INVALID_ENTITY_PATH = 'CONTENT_SYSTEM__INVALID_ENTITY_PATH';
    public const CONTEXT_PATH_NOT_RESOLVABLE = 'CONTENT_SYSTEM__CONTEXT_PATH_NOT_RESOLVABLE';
    public const REDISTRIBUTE_DOTTED_PATH = 'CONTENT_SYSTEM__REDISTRIBUTE_DOTTED_PATH';
    public const REDISTRIBUTE_CONFLICT = 'CONTENT_SYSTEM__REDISTRIBUTE_CONFLICT';
    public const CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE = 'CONTENT_SYSTEM__CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE';
    public const PAGE_CONTEXT_EXTRACTION_FAILED = 'CONTENT_SYSTEM__PAGE_CONTEXT_EXTRACTION_FAILED';

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

    public static function unsupportedEntityType(string $entityType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_ENTITY_TYPE,
            'Entity type "{{ entityType }}" is not supported for content layout resolution',
            ['entityType' => $entityType]
        );
    }

    public static function unsupportedFactoryType(string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_FACTORY_TYPE,
            'Context factory type "{{ type }}" is not registered. Available types: route, product, category',
            ['type' => $type]
        );
    }

    public static function invalidRequestParameter(string $parameterName, string $expectedType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER,
            'Request parameter "{{ parameterName }}" must be a {{ expectedType }}',
            ['parameterName' => $parameterName, 'expectedType' => $expectedType]
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

    public static function invalidProductPath(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PRODUCT_PATH,
            'Invalid product path format: "{{ path }}". Expected format: /product/{productId}',
            ['path' => $path]
        );
    }

    public static function invalidCategoryPath(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CATEGORY_PATH,
            'Invalid category path format: "{{ path }}". Expected format: /category/{categoryId}',
            ['path' => $path]
        );
    }

    public static function invalidLandingPagePath(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_LANDING_PAGE_PATH,
            'Invalid landing page path format: "{{ path }}". Expected format: /landing-page/{landingPageId}',
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

    public static function pageContextExtractionFailed(string $layoutId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PAGE_CONTEXT_EXTRACTION_FAILED,
            'Failed to extract actual roots from page context virtual root for layout "{{ layoutId }}": {{ reason }}',
            ['layoutId' => $layoutId, 'reason' => $reason],
            $previous
        );
    }
}
