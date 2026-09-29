<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@framework')]
class UserException extends HttpException
{
    public const MISSING_REQUEST_PARAMETER_CODE = 'SYSTEM_USER__MISSING_REQUEST_PARAMETER';
    final public const SALES_CHANNEL_NOT_FOUND = 'USER__SALES_CHANNEL_NOT_FOUND';
    final public const INVALID_APP_URL = 'USER__INVALID_APP_URL';

    public static function missingRequestParameter(string $name, string $path = ''): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $name, 'path' => $path]
        );
    }

    public static function salesChannelNotFound(): HttpException
    {
        return new self(
            Response::HTTP_PRECONDITION_FAILED,
            self::SALES_CHANNEL_NOT_FOUND,
            'No sales channel found.',
        );
    }

    public static function invalidAppUrl(string $appUrl): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_APP_URL,
            'The APP_URL "{{ appUrl }}" is not a valid http(s) URL.',
            ['appUrl' => $appUrl]
        );
    }
}
