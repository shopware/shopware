<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class DocumentException extends HttpException
{
    public const INVALID_DOCUMENT_GENERATOR_TYPE_CODE = 'DOCUMENT__INVALID_GENERATOR_TYPE';

    public const ORDER_NOT_FOUND = 'DOCUMENT__ORDER_NOT_FOUND';

    public const DOCUMENT_NOT_FOUND = 'DOCUMENT__DOCUMENT_NOT_FOUND';

    public const GENERATION_ERROR = 'DOCUMENT__GENERATION_ERROR';

    public const DOCUMENT_NUMBER_ALREADY_EXISTS = 'DOCUMENT__NUMBER_ALREADY_EXISTS';

    public const DOCUMENT_GENERATION_ERROR = 'DOCUMENT__GENERATION_ERROR';

    public const DOCUMENT_INVALID_RENDERER_TYPE = 'DOCUMENT__INVALID_RENDERER_TYPE';

    public const INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__INVALID_REQUEST_PARAMETER';

    public const FILE_EXTENSION_NOT_SUPPORTED = 'DOCUMENT__FILE_EXTENSION_NOT_SUPPORTED';

    public static function invalidDocumentGeneratorType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DOCUMENT_GENERATOR_TYPE_CODE,
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

    public static function customerNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }

    public static function documentNumberAlreadyExistsException(string $number = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_NUMBER_ALREADY_EXISTS,
            \sprintf('Document number %s has already been allocated.', $number),
            [
                '$number' => $number,
            ],
        );
    }

    public static function documentGenerationException(string $message = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_GENERATION_ERROR,
            \sprintf('Unable to generate document. %s', $message),
            [
                '$message' => $message,
            ],
        );
    }

    public static function invalidDocumentRenderer(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DOCUMENT_INVALID_RENDERER_TYPE,
            \sprintf('Unable to find a document renderer with type "%s"', $type),
            [
                '$type' => $type,
            ],
        );
    }

    public static function invalidRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function guestNotAuthenticated(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
            'Guest not authenticated.'
        );
    }

    public static function wrongGuestCredentials(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            OrderException::CHECKOUT_GUEST_WRONG_CREDENTIALS,
            'Wrong credentials for guest authentication.'
        );
    }

    public static function unsupportedDocumentFileExtension(string $fileExtension): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FILE_EXTENSION_NOT_SUPPORTED,
            'File extension not supported: {{ fileExtension }}',
            ['fileExtension' => $fileExtension]
        );
    }

    /**
     * @param array<string, string[]> $violations
     */
    public static function electronicInvoiceViolation(int $count, array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::GENERATION_ERROR,
            'Unable to generate document. {{counter}} violation(s) found',
            [
                'counter' => $count,
                'violations' => $violations,
            ]
        );
    }
}
