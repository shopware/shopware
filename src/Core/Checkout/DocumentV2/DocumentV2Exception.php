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

    public const MISSING_RENDER_PLAN_DEPENDENCY = 'DOCUMENT_V2__MISSING_RENDER_PLAN_DEPENDENCY';

    public const DOCUMENT_NOT_PERSISTED = 'DOCUMENT_V2__DOCUMENT_NOT_PERSISTED';

    public const DOCUMENT_NUMBER_ALREADY_EXISTS = 'DOCUMENT_V2__DOCUMENT_NUMBER_ALREADY_EXISTS';

    public const DOCUMENT_TYPE_NOT_FOUND = 'DOCUMENT_V2__DOCUMENT_TYPE_NOT_FOUND';

    public const DUPLICATE_RENDERER = 'DOCUMENT_V2__DUPLICATE_RENDERER';

    public const DUPLICATE_PROVIDER_KEY = 'DOCUMENT_V2__DUPLICATE_PROVIDER_KEY';

    public const TEMPLATE_RENDER_FAILED = 'DOCUMENT_V2__TEMPLATE_RENDER_FAILED';

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

    public static function documentNumberAlreadyExists(string $documentNumber): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::DOCUMENT_NUMBER_ALREADY_EXISTS,
            'Document with number "{{ documentNumber }}" already exists.',
            ['documentNumber' => $documentNumber],
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

    public static function duplicateRenderer(string $format, string $documentType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_RENDERER,
            'Duplicate renderer for format "{{ format }}" and document type "{{ documentType }}".',
            ['format' => $format, 'documentType' => $documentType],
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
}
