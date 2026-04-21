<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
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

    public const LIVE_VERSION_NOT_ALLOWED = 'DOCUMENT_V2__LIVE_VERSION_NOT_ALLOWED';

    public const ORDER_NOT_FOUND = 'DOCUMENT_V2__ORDER_NOT_FOUND';

    public const RENDERER_NOT_FOUND = 'DOCUMENT_V2__RENDERER_NOT_FOUND';

    public const CIRCULAR_DEPENDENCY_CYCLE = 'DOCUMENT_V2__CIRCULAR_DEPENDENCY_CYCLE';

    public const DOCUMENT_NOT_PERSISTED = 'DOCUMENT_V2__DOCUMENT_NOT_PERSISTED';

    public const DOCUMENT_TYPE_NOT_FOUND = 'DOCUMENT_V2__DOCUMENT_TYPE_NOT_FOUND';

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

    public static function liveVersionNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LIVE_VERSION_NOT_ALLOWED,
            'Live version of document is not allowed for document generation.',
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

    public static function rendererNotFound(string $format, string $documentType): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::RENDERER_NOT_FOUND,
            'Renderer for format "{{ format }}" and document type "{{ documentType }}" not found.',
            ['format' => $format, 'documentType' => $documentType],
        );
    }

    public static function circularRenderDependency(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CIRCULAR_DEPENDENCY_CYCLE,
            'Circular render dependency cycled for document generation.',
        );
    }

    public static function documentNotPersisted(string $documentId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DOCUMENT_NOT_PERSISTED,
            'Document with id "{{ documentId }}" is not persisted.',
            ['documentId' => $documentId],
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
}
