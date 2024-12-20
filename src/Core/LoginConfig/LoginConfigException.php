<?php

declare(strict_types=1);

namespace Shopware\Core\LoginConfig;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class LoginConfigException extends HttpException
{
    final public const LOGIN_CONFIG_INCOMPLETE = 'LOGIN_CONFIG__INCOMPLETE';

    final public const LOGIN_CONFIG_HANDLER_NOT_FOUND = 'LOGIN_CONFIG__HANDLER_NOT_FOUND';

    final public const LOGIN_CONFIG_FOR_KEY_NOT_FOUND = 'LOGIN_CONFIG__CONFIG_FOR_KEY_NOT_FOUND';

    public static function configurationIncomplete(array $fields): self
    {
        $fields = implode(', ', $fields);
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_INCOMPLETE,
            'Login config is incomplete. Required field(s) "{{ fields }}" missing.',
            ['fields' => $fields]
        );
    }

    public static function handlerNotFound(string $configKey): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_HANDLER_NOT_FOUND,
            'No handler found for login config "{{ configKey }}".',
            ['configKey' => $configKey]
        );
    }

    public static function configForKeyNotFound(string $key): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LOGIN_CONFIG_FOR_KEY_NOT_FOUND,
            'Login config for key: {{ key }} not found.',
            ['key' => $key]
        );
    }
}
