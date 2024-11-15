<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentRendererException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class DocumentException extends HttpException
{
    public const INVALID_DOCUMENT_GENERATOR_TYPE_CODE = 'DOCUMENT__INVALID_GENERATOR_TYPE';

    public const INVALID_DOCUMENT_RENDERER_TYPE = 'DOCUMENT__INVALID_RENDERER_TYPE';

    public const ORDER_NOT_FOUND = 'DOCUMENT__ORDER_NOT_FOUND';

    public const DOCUMENT_NOT_FOUND = 'DOCUMENT__DOCUMENT_NOT_FOUND';

    public const DOCUMENT_NUMBER_ALREADY_EXISTS = 'DOCUMENT__NUMBER_ALREADY_EXISTS';

    public const GENERATION_ERROR = 'DOCUMENT__GENERATION_ERROR';

    public static function invalidDocumentGeneratorType(string $type): self
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidDocumentGeneratorTypeException(
                Response::HTTP_BAD_REQUEST,
                self::INVALID_DOCUMENT_GENERATOR_TYPE_CODE,
                'Unable to find a document generator with type "{{ type }}"',
                ['type' => $type]
            );
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOCUMENT_GENERATOR_TYPE_CODE,
            'Unable to find a document generator with type "{{ type }}"',
            ['type' => $type]
        );
    }

    public static function invalidDocumentRendererType(string $type): self
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidDocumentRendererException($type);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOCUMENT_RENDERER_TYPE,
            'Unable to find a document renderer with type "{{ type }}"',
            ['type' => $type]
        );
    }

    public static function orderNotFound(string $orderId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'The order with id {{ orderId }} is invalid or could not be found.',
            ['orderId' => $orderId],
            $e
        );
    }

    public static function documentNotFound(string $documentId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_NOT_FOUND,
            'The document with id "{{ documentId }}" is invalid or could not be found.',
            ['documentId' => $documentId],
            $e
        );
    }

    public static function documentAlreadyExists(string $number): self
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new DocumentNumberAlreadyExistsException($number);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_NUMBER_ALREADY_EXISTS,
            'Document number {{number}} has already been allocated.',
            ['number' => $number]
        );
    }

    /**
     * @deprecated tag:v6.7.0 - Will be removed, use DocumentException::generationError instead
     */
    public static function legacyGenerationError(string $message = '', int $statusCode = Response::HTTP_NOT_FOUND): self
    {
        if (!Feature::isActive('v6.7.0.0')) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'DocumentException::generationError')
            );

            return new DocumentGenerationException($message);
        }

        return self::generationError($message, null, $statusCode);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:new-optional-parameter - Parameter int $statusCode = Response::HTTP_NOT_FOUND will be added
     */
    public static function generationError(?string $message = null, ?\Throwable $e = null/* , int $statusCode = Response::HTTP_NOT_FOUND */): self
    {
        $statusCode = (\func_num_args() === 3) ? \func_get_arg(2) : Response::HTTP_NOT_FOUND;

        return new self(
            $statusCode,
            self::GENERATION_ERROR,
            'Unable to generate document. {{message}}',
            ['message' => $message],
            $e
        );
    }
}
