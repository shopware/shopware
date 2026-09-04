<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.8.0 feature:DOCUMENT_GENERATION_REWORK
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class DocumentV2Exception extends HttpException
{
    public const UNKNOWN_RENDER_DATA = 'DOCUMENT_V2__UNKNOWN_RENDER_DATA';

    public const UNKNOWN_RENDER_RESULT = 'DOCUMENT_V2__UNKNOWN_RENDER_RESULT';

    public const DUPLICATE_RENDER_RESULT = 'DOCUMENT_V2__DUPLICATE_RENDER_RESULT';

    public const MISSING_FORMATS = 'DOCUMENT_V2__MISSING_FORMATS';

    public const ORDER_NOT_FOUND = 'DOCUMENT_V2__ORDER_NOT_FOUND';

    public const DOCUMENT_NOT_FOUND = 'DOCUMENT_V2__DOCUMENT_NOT_FOUND';

    public const RENDERER_NOT_FOUND = 'DOCUMENT_V2__RENDERER_NOT_FOUND';

    public const UNSUPPORTED_DOCUMENT_FORMAT = 'DOCUMENT_V2__UNSUPPORTED_DOCUMENT_FORMAT';

    public const CIRCULAR_DEPENDENCY_CYCLE = 'DOCUMENT_V2__CIRCULAR_DEPENDENCY_CYCLE';

    public const MISSING_RENDER_PLAN_DEPENDENCY = 'DOCUMENT_V2__MISSING_RENDER_PLAN_DEPENDENCY';

    public const DOCUMENT_NOT_PERSISTED = 'DOCUMENT_V2__DOCUMENT_NOT_PERSISTED';

    public const DOCUMENT_NUMBER_ALREADY_EXISTS = 'DOCUMENT_V2__DOCUMENT_NUMBER_ALREADY_EXISTS';

    public const DOCUMENT_TYPE_NOT_FOUND = 'DOCUMENT_V2__DOCUMENT_TYPE_NOT_FOUND';

    public const DUPLICATE_PROVIDER_KEY = 'DOCUMENT_V2__DUPLICATE_PROVIDER_KEY';

    public const TEMPLATE_RENDER_FAILED = 'DOCUMENT_V2__TEMPLATE_RENDER_FAILED';

    public const CONFIG_MISSING_REQUIRED_FIELDS = 'DOCUMENT_V2__CONFIG_MISSING_REQUIRED_FIELDS';

    public const TEMPLATE_CONTEXT_READ_ONLY = 'DOCUMENT_V2__TEMPLATE_CONTEXT_READ_ONLY';

    public const TEMPLATE_CONTEXT_PROPERTY_COLLISION = 'DOCUMENT_V2__TEMPLATE_CONTEXT_PROPERTY_COLLISION';

    public const UNSUPPORTED_CONFIG_CAST_TYPE = 'DOCUMENT_V2__UNSUPPORTED_CONFIG_CAST_TYPE';

    public const MISSING_DOCUMENT_NUMBER = 'DOCUMENT_V2__MISSING_DOCUMENT_NUMBER';

    public const MISSING_DELIVERY_DATE = 'DOCUMENT_V2__MISSING_DELIVERY_DATE';

    public const MALFORMED_XML = 'DOCUMENT_V2__MALFORMED_XML';

    public const INVALID_ORDER_DATA = 'DOCUMENT_V2__INVALID_ORDER_DATA';

    public const INVALID_RENDER_VALUE = 'DOCUMENT_V2__INVALID_RENDER_VALUE';

    public const INVALID_DOCUMENT_TYPE = 'DOCUMENT_V2__INVALID_DOCUMENT_TYPE';

    public const INVALID_REQUEST_PARAMETER = 'DOCUMENT_V2__INVALID_REQUEST_PARAMETER';

    public const DOCUMENT_FORMAT_UNAVAILABLE = 'DOCUMENT_V2__FORMAT_UNAVAILABLE';

    public const DOCUMENT_FILE_EXTENSION_UNAVAILABLE = 'DOCUMENT_V2__FILE_EXTENSION_UNAVAILABLE';

    public const DOCUMENT_ARCHIVE_UNAVAILABLE = 'DOCUMENT_V2__ARCHIVE_UNAVAILABLE';

    public const DOCUMENT_ARCHIVE_LIMIT_EXCEEDED = 'DOCUMENT_V2__ARCHIVE_LIMIT_EXCEEDED';

    public const DOCUMENT_ARCHIVE_FAILED = 'DOCUMENT_V2__ARCHIVE_FAILED';

    public const EMBED_FAILED = 'DOCUMENT_V2__EMBED_FAILED';

    public const REFERENCED_INVOICE_NOT_FOUND = 'DOCUMENT_V2__REFERENCED_INVOICE_NOT_FOUND';

    public const REFERENCED_INVOICE_NUMBER_MISSING = 'DOCUMENT_V2__REFERENCED_INVOICE_NUMBER_MISSING';

    public const REFERENCED_ORDER_VERSION_NOT_FOUND = 'DOCUMENT_V2__REFERENCED_ORDER_VERSION_NOT_FOUND';

    public const REFERENCED_DOCUMENT_NOT_SUPPORTED = 'DOCUMENT_V2__REFERENCED_DOCUMENT_NOT_SUPPORTED';

    public const NO_CREDIT_LINE_ITEMS = 'DOCUMENT_V2__NO_CREDIT_LINE_ITEMS';

    public const NO_UNPROCESSED_CREDIT_LINE_ITEMS = 'DOCUMENT_V2__NO_UNPROCESSED_CREDIT_LINE_ITEMS';

    public const DOCUMENT_TYPE_ALREADY_REGISTERED = 'DOCUMENT_V2__DOCUMENT_TYPE_ALREADY_REGISTERED';

    public const DOCUMENT_TYPE_SHADOWS_CORE_TYPE = 'DOCUMENT_V2__DOCUMENT_TYPE_SHADOWS_CORE_TYPE';

    /**
     * @deprecated tag:v6.9.0 - reason:experimental-replacement - Remove with the `app_provided` sentinel once `document.document_type_id` is dropped.
     */
    public const DOCUMENT_TYPE_RESERVED_IDENTIFIER = 'DOCUMENT_V2__DOCUMENT_TYPE_RESERVED_IDENTIFIER';

    public static function unknownRenderData(string $key, string $expectedClass): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_RENDER_DATA,
            'Unknown render data for key "{{ key }}", expected instance of "{{ expectedClass }}".',
            ['key' => $key, 'expectedClass' => $expectedClass],
        );
    }

    public static function unknownRenderResult(string $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNKNOWN_RENDER_RESULT,
            'Unknown render result for format "{{ format }}".',
            ['format' => $format],
        );
    }

    public static function duplicateRenderResult(string $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_RENDER_RESULT,
            'Duplicate render result for format "{{ format }}".',
            ['format' => $format],
        );
    }

    public static function missingFormats(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_FORMATS,
            'Missing formats for document generation.',
        );
    }

    public static function orderNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'Order with id "{{ orderId }}" not found.',
            ['orderId' => $orderId],
        );
    }

    public static function documentNotFound(string $documentId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_NOT_FOUND,
            'Document with id "{{ documentId }}" not found.',
            ['documentId' => $documentId],
        );
    }

    public static function rendererNotFound(string $format, ?string $documentType = null): self
    {
        $message = $documentType === null
            ? 'Renderer for format "{{ format }}" not found.'
            : 'Renderer for format "{{ format }}" and document type "{{ documentType }}" not found.';

        return new self(
            Response::HTTP_NOT_FOUND,
            self::RENDERER_NOT_FOUND,
            $message,
            ['format' => $format, 'documentType' => $documentType],
        );
    }

    public static function unsupportedDocumentFormat(string $format, string $documentType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_DOCUMENT_FORMAT,
            'Unsupported document format "{{ format }}" for document type "{{ documentType }}".',
            ['format' => $format, 'documentType' => $documentType],
        );
    }

    public static function invalidDocumentType(string $documentType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOCUMENT_TYPE,
            'Invalid document type "{{ documentType }}". A document type must only contain lowercase letters, digits and underscores.',
            ['documentType' => $documentType],
        );
    }

    public static function documentTypeAlreadyRegistered(string $identifier, string $owningApp): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_TYPE_ALREADY_REGISTERED,
            'The document type "{{ identifier }}" is already registered by app "{{ owningApp }}".',
            ['identifier' => $identifier, 'owningApp' => $owningApp],
        );
    }

    public static function documentTypeShadowsCoreType(string $identifier): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_TYPE_SHADOWS_CORE_TYPE,
            'The document type "{{ identifier }}" shadows a core document type and cannot be registered by an app.',
            ['identifier' => $identifier],
        );
    }

    /**
     * @deprecated tag:v6.9.0 - reason:experimental-replacement - Remove with the `app_provided` sentinel once `document.document_type_id` is dropped.
     */
    public static function documentTypeReservedIdentifier(string $identifier): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_TYPE_RESERVED_IDENTIFIER,
            'The document type "{{ identifier }}" is a reserved technical name and cannot be registered by an app.',
            ['identifier' => $identifier],
        );
    }

    public static function invalidRequestParameter(string $parameter): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $parameter],
        );
    }

    public static function documentFormatUnavailable(string $documentId, string $format): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_FORMAT_UNAVAILABLE,
            'Document with id "{{ documentId }}" has no generated document with format "{{ format }}".',
            ['documentId' => $documentId, 'format' => $format],
        );
    }

    public static function documentFileExtensionUnavailable(string $documentId, string $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DOCUMENT_FILE_EXTENSION_UNAVAILABLE,
            'Document with id "{{ documentId }}" has no file extension for format "{{ format }}".',
            ['documentId' => $documentId, 'format' => $format],
        );
    }

    /**
     * @param list<string> $documentIds
     */
    public static function documentArchiveUnavailable(array $documentIds): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_ARCHIVE_UNAVAILABLE,
            'None of the requested documents have generated files to archive: "{{ documentIds }}".',
            ['documentIds' => implode(', ', $documentIds)],
        );
    }

    public static function documentArchiveLimitExceeded(int $requested, int $limit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_ARCHIVE_LIMIT_EXCEEDED,
            'Cannot archive {{ requested }} documents at once, the limit is {{ limit }}.',
            ['requested' => $requested, 'limit' => $limit],
        );
    }

    public static function documentArchiveFailed(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DOCUMENT_ARCHIVE_FAILED,
            'Failed to create document archive.',
        );
    }

    /**
     * @param list<string> $remaining
     */
    public static function circularRenderDependency(array $remaining): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CIRCULAR_DEPENDENCY_CYCLE,
            'Circular render dependency cycled for document generation. '
                . 'Remaining formats with circular dependency: {{ remaining }}.',
            ['remaining' => implode(', ', $remaining)],
        );
    }

    public static function missingRenderPlanDependency(string $format): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_RENDER_PLAN_DEPENDENCY,
            'Dependency format "{{ format }}" is missing from the resolved render plan.',
            ['format' => $format],
        );
    }

    public static function documentNotPersisted(string $documentNumber): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DOCUMENT_NOT_PERSISTED,
            'Document with number "{{ documentNumber }}" was not persisted.',
            ['documentNumber' => $documentNumber],
        );
    }

    public static function documentNumberAlreadyExists(string $documentNumber, string $documentType = ''): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::DOCUMENT_NUMBER_ALREADY_EXISTS,
            $documentType !== ''
                ? 'Document with number "{{ documentNumber }}" already exists for document type "{{ documentType }}".'
                : 'Document with number "{{ documentNumber }}" already exists.',
            ['documentNumber' => $documentNumber, 'documentType' => $documentType],
        );
    }

    public static function documentTypeNotFound(string $documentType): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_TYPE_NOT_FOUND,
            'Document type "{{ documentType }}" not found.',
            ['documentType' => $documentType],
        );
    }

    public static function duplicateProviderKey(string $key, string $documentType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_PROVIDER_KEY,
            'Duplicate document data provider key "{{ key }}" for document type "{{ documentType }}".',
            ['key' => $key, 'documentType' => $documentType],
        );
    }

    public static function templateRenderFailed(string $view, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_RENDER_FAILED,
            'Failed to render document template "{{ view }}": {{ reason }}.',
            ['view' => $view, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function configMissingRequiredFields(string $target, string $documentType, string $field): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONFIG_MISSING_REQUIRED_FIELDS,
            'Document configuration for document type "{{ documentType }}" is missing required field "{{ field }}" for "{{ target }}".',
            ['documentType' => $documentType, 'target' => $target, 'field' => $field],
        );
    }

    public static function templateContextReadOnly(string $offset): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_CONTEXT_READ_ONLY,
            'TemplateContext is read-only; cannot mutate offset "{{ offset }}".',
            ['offset' => $offset],
        );
    }

    public static function templateContextPropertyCollision(string $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TEMPLATE_CONTEXT_PROPERTY_COLLISION,
            'Type-specific render data cannot override the shared property "{{ property }}".',
            ['property' => $property],
        );
    }

    public static function unsupportedConfigCastType(string $type): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNSUPPORTED_CONFIG_CAST_TYPE,
            'Unsupported document config cast type "{{ type }}".',
            ['type' => $type],
        );
    }

    public static function missingDocumentNumber(string $documentType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_DOCUMENT_NUMBER,
            'Document number is missing for document type "{{ documentType }}".',
            ['documentType' => $documentType],
        );
    }

    public static function referencedInvoiceNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REFERENCED_INVOICE_NOT_FOUND,
            'Cannot generate cancellation invoice because no invoice document exists for order "{{ orderId }}".',
            ['orderId' => $orderId],
        );
    }

    public static function referencedInvoiceNumberMissing(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REFERENCED_INVOICE_NUMBER_MISSING,
            'Cannot generate cancellation invoice because the referenced invoice for order "{{ orderId }}" has no document number.',
            ['orderId' => $orderId],
        );
    }

    public static function referencedOrderVersionNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REFERENCED_ORDER_VERSION_NOT_FOUND,
            'Cannot resolve the order snapshot captured by the referenced document for order "{{ orderId }}".',
            ['orderId' => $orderId],
        );
    }

    public static function referencedDocumentNotSupported(string $documentType, string $referencedDocumentId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::REFERENCED_DOCUMENT_NOT_SUPPORTED,
            'Document type "{{ documentType }}" does not support a referenced document, but referenced document id "{{ referencedDocumentId }}" was supplied.',
            ['documentType' => $documentType, 'referencedDocumentId' => $referencedDocumentId],
        );
    }

    public static function missingDeliveryDate(string $documentType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_DELIVERY_DATE,
            'Delivery date is required for document type "{{ documentType }}".',
            ['documentType' => $documentType],
        );
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function malformedXml(int $count, array $errors): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MALFORMED_XML,
            'Generated XML is malformed with {{ count }} violation(s): {{ errors }}.',
            [
                'count' => $count,
                'errors' => json_encode($errors),
            ],
        );
    }

    public static function invalidOrderData(string $orderId, string $field, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ORDER_DATA,
            'Order "{{ orderId }}" has invalid data for field "{{ field }}": {{ reason }}.',
            ['orderId' => $orderId, 'field' => $field, 'reason' => $reason],
        );
    }

    public static function invalidRenderValue(string $field, string $value, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_RENDER_VALUE,
            'Invalid render value for field "{{ field }}": {{ value }} ({{ reason }}).',
            ['field' => $field, 'value' => $value, 'reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function embedFailed(\Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::EMBED_FAILED,
            'Failed to embed the XML into the PDF: {{ reason }}.',
            ['reason' => $previous->getMessage()],
            $previous,
        );
    }

    public static function noCreditLineItems(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_CREDIT_LINE_ITEMS,
            'Cannot generate credit note because order "{{ orderId }}" has no credit line items.',
            ['orderId' => $orderId],
        );
    }

    public static function noUnprocessedCreditLineItems(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_UNPROCESSED_CREDIT_LINE_ITEMS,
            'Cannot generate credit note because every credit line item of order "{{ orderId }}" is already invoiced or credited.',
            ['orderId' => $orderId],
        );
    }
}
