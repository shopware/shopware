<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Document\Exception\DocumentGenerationException;
use Shopware\Core\Checkout\Document\Exception\DocumentNumberAlreadyExistsException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentGeneratorTypeException;
use Shopware\Core\Checkout\Document\Exception\InvalidDocumentRendererException;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Feature;
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
        return new InvalidDocumentGeneratorTypeException(
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

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function customerNotLoggedIn(): self|CustomerNotLoggedInException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_FORBIDDEN,
                CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
                'Customer is not logged in.'
            );
        }

        return new CustomerNotLoggedInException(
            Response::HTTP_FORBIDDEN,
            CartException::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function documentNumberAlreadyExistsException(string $number = ''): self|DocumentNumberAlreadyExistsException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_BAD_REQUEST,
                self::DOCUMENT_NUMBER_ALREADY_EXISTS,
                \sprintf('Document number %s has already been allocated.', $number),
                [
                    '$number' => $number,
                ],
            );
        }

        return new DocumentNumberAlreadyExistsException($number);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function documentGenerationException(string $message = ''): self|DocumentGenerationException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_BAD_REQUEST,
                self::DOCUMENT_GENERATION_ERROR,
                \sprintf('Unable to generate document. %s', $message),
                [
                    '$message' => $message,
                ],
            );
        }

        return new DocumentGenerationException($message);
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function invalidDocumentRenderer(string $type): self|InvalidDocumentRendererException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_BAD_REQUEST,
                self::DOCUMENT_INVALID_RENDERER_TYPE,
                \sprintf('Unable to find a document renderer with type "%s"', $type),
                [
                    '$type' => $type,
                ],
            );
        }

        return new InvalidDocumentRendererException($type);
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

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function guestNotAuthenticated(): self|GuestNotAuthenticatedException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_FORBIDDEN,
                OrderException::CHECKOUT_GUEST_NOT_AUTHENTICATED,
                'Guest not authenticated.'
            );
        }

        return new GuestNotAuthenticatedException();
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return self
     */
    public static function wrongGuestCredentials(): self|WrongGuestCredentialsException
    {
        if (Feature::isActive('v6.7.0.0')) {
            return new self(
                Response::HTTP_FORBIDDEN,
                OrderException::CHECKOUT_GUEST_WRONG_CREDENTIALS,
                'Wrong credentials for guest authentication.'
            );
        }

        return new WrongGuestCredentialsException();
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
}
