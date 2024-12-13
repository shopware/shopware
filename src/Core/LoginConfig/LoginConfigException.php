<?php

declare(strict_types=1);

namespace Shopware\Core\LoginConfig;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class LoginConfigException extends HttpException
{
    final public const LOGIN_CONFIG_SESSION_NOT_SET = 'LOGIN_CONFIG__SESSION_IS_NOT_SET';

    public static function sessionIsNotSet(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_SESSION_NOT_SET,
            'Session not set'
        );
    }
}
