<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\CustomerNotLoggedInRoutingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class RoutingException extends HttpException
{
    public const MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__MISSING_REQUEST_PARAMETER';
    public const INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__INVALID_REQUEST_PARAMETER';
    public const APP_INTEGRATION_NOT_FOUND = 'FRAMEWORK__APP_INTEGRATION_NOT_FOUND';
    public const LANGUAGE_NOT_FOUND = 'FRAMEWORK__LANGUAGE_NOT_FOUND';
    public const SALES_CHANNEL_MAINTENANCE_MODE = 'FRAMEWORK__ROUTING_SALES_CHANNEL_MAINTENANCE';

    public const CUSTOMER_NOT_LOGGED_IN_CODE = 'FRAMEWORK__ROUTING_CUSTOMER_NOT_LOGGED_IN';
    public const ACCESS_DENIED_FOR_XML_HTTP_REQUEST = 'FRAMEWORK__ACCESS_DENIED_FOR_XML_HTTP_REQUEST';
    public const CURRENCY_NOT_FOUND = 'FRAMEWORK__ROUTING_CURRENCY_NOT_FOUND';
    public const MISSING_PRIVILEGE = 'FRAMEWORK__ROUTING_MISSING_PRIVILEGE';

    public static function invalidRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function languageNotFound(?string $languageId): self
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'language', 'field' => 'id', 'value' => $languageId]
        );
    }

    public static function appIntegrationNotFound(string $integrationId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_INTEGRATION_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'app integration', 'field' => 'id', 'value' => $integrationId]
        );
    }

    public static function customerNotLoggedIn(): CustomerNotLoggedInRoutingException
    {
        return new CustomerNotLoggedInRoutingException(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN_CODE,
            'Customer is not logged in.'
        );
    }

    public static function accessDeniedForXmlHttpRequest(?string $route = null, ?string $url = null, ?string $referer = null): self
    {
        $message = 'PageController ' . ($route ? '"{{ route }}" ' : '')
            . ($url ? '("{{ url }}") ' : '')
            . 'can\'t be requested via XmlHttpRequest.'
            . ($referer ? ' Requested by "{{ referer }}".' : '');

        return new self(
            Response::HTTP_FORBIDDEN,
            self::ACCESS_DENIED_FOR_XML_HTTP_REQUEST,
            $message,
            ['route' => $route, 'url' => $url, 'referer' => $referer]
        );
    }

    public static function currencyNotFound(string $currencyId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CURRENCY_NOT_FOUND,
            'Currency with id "{{ currencyId }}" not found.',
            ['currencyId' => $currencyId]
        );
    }

    /**
     * @param string[] $privileges
     */
    public static function missingPrivileges(array $privileges): self
    {
        $errorMessage = json_encode([
            'message' => 'Missing privilege',
            'missingPrivileges' => $privileges,
        ], \JSON_THROW_ON_ERROR);

        return new self(
            Response::HTTP_FORBIDDEN,
            self::MISSING_PRIVILEGE,
            $errorMessage ?: ''
        );
    }
}
