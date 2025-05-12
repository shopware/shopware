<?php declare(strict_types=1);

namespace Shopware\Core\Content\SaasUser;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class SaasUserException extends HttpException
{
    public const SAAS_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND = 'SAAS_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND';

    public const SAAS_USER_INVITATION_NO_LOGIN_CONFIG = 'SAAS_USER_INVITATION_NO_LOGIN_CONFIG';

    public static function mailTemplateNotFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SAAS_USER_INVITATION_MAIL_TEMPLATE_NOT_FOUND,
            'Mail template for saas user invitation not found'
        );
    }

    public static function noLoginConfig(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SAAS_USER_INVITATION_NO_LOGIN_CONFIG,
            'Login config for saas user invitation not found'
        );
    }
}
