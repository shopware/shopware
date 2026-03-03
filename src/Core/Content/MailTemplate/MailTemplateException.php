<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\Event\EventData\EventDataType;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class MailTemplateException extends HttpException
{
    public const MAIL_INVALID_TEMPLATE_CONTENT = 'CONTENT__INVALID_MAIL_TEMPLATE_CONTENT';
    public const MAIL_TEMPLATE_NOT_FOUND = 'CONTENT__MAIL_TEMPLATE_NOT_FOUND';
    public const MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE = 'CONTENT__MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE';

    public static function invalidMailTemplateContent(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAIL_INVALID_TEMPLATE_CONTENT,
            'Invalid Mail Template content under "mailTemplate.contentHtml" parameter, please send the plain template as string.'
        );
    }

    public static function templateNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAIL_TEMPLATE_NOT_FOUND,
            'Mail Template not found.'
        );
    }

    /**
     * @param class-string<EventDataType> $dataTypeClass
     */
    public static function unknownEventDataType(string $dataTypeClass): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE,
            'Unknown event data type: ' . $dataTypeClass,
        );
    }
}
