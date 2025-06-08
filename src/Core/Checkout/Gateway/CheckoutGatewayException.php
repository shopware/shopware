<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CheckoutGatewayException extends HttpException
{
    public const EMPTY_APP_RESPONSE_CODE = 'CHECKOUT_GATEWAY__EMPTY_APP_RESPONSE';
    public const PAYLOAD_INVALID_CODE = 'CHECKOUT_GATEWAY__PAYLOAD_INVALID';

    public const HANDLER_NOT_FOUND_CODE = 'CHECKOUT_GATEWAY__HANDLER_NOT_FOUND';

    public const HANDLER_EXCEPTION = 'CHECKOUT_GATEWAY__HANDLER_EXCEPTION';

    public static function emptyAppResponse(string $appName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::EMPTY_APP_RESPONSE_CODE,
            'App "{{ app }}" did not provide checkout gateway response',
            ['app' => $appName]
        );
    }

    public static function payloadInvalid(?string $commandKey = null): self
    {
        $message = 'Payload invalid for command';

        if ($commandKey !== null) {
            $message .= ' "{{ command }}"';
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYLOAD_INVALID_CODE,
            $message,
            ['command' => $commandKey]
        );
    }

    public static function handlerNotFound(string $commandKey): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::HANDLER_NOT_FOUND_CODE,
            'Handler not found for command "{{ key }}"',
            ['key' => $commandKey]
        );
    }

    /**
     * @param array<string, string|\Stringable> $parameters
     */
    public static function handlerException(string $message, array $parameters = []): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::HANDLER_EXCEPTION,
            $message,
            $parameters
        );
    }
}
