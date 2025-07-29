<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SsoException extends HttpException
{
    public const SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND = 'SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND';

    public const SSO_USER_INVITATION_NO_LOGIN_CONFIG = 'SSO_USER_INVITATION_NO_LOGIN_CONFIG';

    public static function mailTemplateNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND,
            'Mail template for sso user invitation not found'
        );
    }

    public static function noLoginConfig(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SSO_USER_INVITATION_NO_LOGIN_CONFIG,
            'Login config for sso user invitation not found'
        );
    }
}
