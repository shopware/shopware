<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

#[Package('fundamentals@framework')]
class UserException extends HttpException
{
    public const MISSING_REQUEST_PARAMETER_CODE = 'SYSTEM_USER__MISSING_REQUEST_PARAMETER';
    final public const SALES_CHANNEL_NOT_FOUND = 'USER__SALES_CHANNEL_NOT_FOUND';
    final public const INVALID_APP_URL = 'USER__INVALID_APP_URL';
    final public const USER_CHANGE_NOT_ALLOWED = 'USER__USER_CHANGE_NOT_ALLOWED';

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

    /**
     * The same error as for an immutable field on 6.7.7 and later, where `user_access_key.user_id` is immutable.
     */
    public static function accessKeyUserNotChangeable(): WriteConstraintViolationException
    {
        $message = 'The field "user_id" of "user_access_key" is immutable and cannot be updated.';

        return new WriteConstraintViolationException(new ConstraintViolationList([
            new ConstraintViolation($message, $message, ['field' => 'user_id', 'entity' => 'user_access_key'], null, 'user_id', null),
        ]));
    }

    public static function userChangeNotAllowed(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::USER_CHANGE_NOT_ALLOWED,
            'A user and its access keys can only be changed or deleted by that user or an admin.'
        );
    }
}
