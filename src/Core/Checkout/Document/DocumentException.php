<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentRendererException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class DocumentException extends HttpException
{
    public const INVALID_DOCUMENT_GENERATOR_TYPE_CODE = 'DOCUMENT__INVALID_GENERATOR_TYPE';

    public const ORDER_NOT_FOUND = 'DOCUMENT__ORDER_NOT_FOUND';

    public const DOCUMENT_NOT_FOUND = 'DOCUMENT__DOCUMENT_NOT_FOUND';

    public const GENERATION_ERROR = 'DOCUMENT__GENERATION_ERROR';

    public const DOCUMENT_NUMBER_ALREADY_EXISTS = 'DOCUMENT__NUMBER_ALREADY_EXISTS';

    public const DOCUMENT_GENERATION_ERROR = 'DOCUMENT__GENERATION_ERROR';

    public const DOCUMENT_INVALID_RENDERER_TYPE = 'DOCUMENT__INVALID_RENDERER_TYPE';

    public static function invalidDocumentGeneratorType(string $type): self
    {
        return new InvalidDocumentGeneratorTypeException(
            Response::HTTP_BAD_REQUEST,
            DocumentException::INVALID_DOCUMENT_GENERATOR_TYPE_CODE,
            'Unable to find a document generator with type "{{ type }}"',
            ['type' => $type]
        );
    }

    public static function orderNotFound(string $orderId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_NOT_FOUND,
            'The order with id {{ orderId }} is invalid or could not be found.',
            [
                'orderId' => $orderId,
            ],
            $e
        );
    }

    public static function documentNotFound(string $documentId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DOCUMENT_NOT_FOUND,
            'The document with id "{{ documentId }}" is invalid or could not be found.',
            [
                'documentId' => $documentId,
            ],
            $e
        );
    }

    public static function generationError(?string $message = null, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::GENERATION_ERROR,
            \sprintf('Unable to generate document. %s', $message),
            [
                '$message' => $message,
            ],
            $e
        );
    }

    public static function customerNotLoggedIn(): CustomerNotLoggedInException
    {
        return new CustomerNotLoggedInException(
            Response::HTTP_FORBIDDEN,
            CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }

    public static function documentNumberAlreadyExistsException(string $number = ''): self|DocumentNumberAlreadyExistsException
    {
        return new DocumentNumberAlreadyExistsException($number);
    }

    public static function documentGenerationException(string $message = ''): self|DocumentGenerationException
    {
        return new DocumentGenerationException($message);
    }

    public static function invalidDocumentRenderer(string $type): self|InvalidDocumentRendererException
    {
        return new InvalidDocumentRendererException($type);
    }
}
