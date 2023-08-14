<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class RoutingException extends HttpException
{
    public const MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__MISSING_REQUEST_PARAMETER';
    public const INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__INVALID_REQUEST_PARAMETER';
    public const APP_INTEGRATION_NOT_FOUND = 'FRAMEWORK__APP_INTEGRATION_NOT_FOUND';
    public const LANGUAGE_NOT_FOUND = 'FRAMEWORK__LANGUAGE_NOT_FOUND';

    public static function invalidRequestParameter(string $name): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new InvalidRequestParameterException($name);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new MissingRequestParameterException($name, $path);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function languageNotFound(?string $languageId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new LanguageNotFoundException($languageId);
        }

        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::LANGUAGE_NOT_FOUND,
            'The language "{{ languageId }}" was not found.',
            ['languageId' => $languageId]
        );
    }

    public static function appIntegrationNotFound(string $integrationId): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_INTEGRATION_NOT_FOUND,
            'App integration "{{ integrationId }}" not found.',
            ['integrationId' => $integrationId],
        );
    }
}
