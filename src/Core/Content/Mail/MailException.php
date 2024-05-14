<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class MailException extends HttpException
{
    final public const GIVEN_OPTION_INVALID = 'MAIL__GIVEN_OPTION_INVALID';

    final public const GIVEN_AGENT_INVALID = 'MAIL__GIVEN_AGENT_INVALID';

    final public const MAIL_BODY_TOO_LONG = 'MAIL__MAIL_BODY_TOO_LONG';

    /**
     * @param string[] $validOptions
     */
    public static function givenSendMailOptionIsInvalid(string $option, array $validOptions): ShopwareHttpException
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::GIVEN_OPTION_INVALID,
            'Given sendmail option "{{ option }}" is invalid. Available options: {{ validOptions }}',
            ['option' => $option, 'validOptions' => implode(', ', $validOptions)]
        );
    }

    public static function givenMailAgentIsInvalid(string $agent): ShopwareHttpException
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::GIVEN_AGENT_INVALID,
            'Invalid mail agent given "{{ agent }}"',
            ['agent' => $agent]
        );
    }

    public static function mailBodyTooLong(int $maxContentLength): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAIL_BODY_TOO_LONG,
            'Mail body is too long. Maximum allowed length is {{ maxContentLength }}',
            ['maxContentLength' => $maxContentLength]
        );
    }
}
