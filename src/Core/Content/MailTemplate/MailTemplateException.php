<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('after-sales')]
class MailTemplateException extends HttpException
{
    public const MAIL_INVALID_TEMPLATE_CONTENT = 'CONTENT__INVALID_MAIL_TEMPLATE_CONTENT';
    public const MAIL_TEMPLATE_NOT_FOUND = 'CONTENT__MAIL_TEMPLATE_NOT_FOUND';
    public const MAIL_TEMPLATE_MISSING_DATA_PROVIDER = 'CONTENT__MAIL_TEMPLATE_MISSING_DATA_PROVIDER';
    public const MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE = 'CONTENT__MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE';
    public const MAIL_TEMPLATE_UNKNOWN_FIELD_TYPE = 'CONTENT__MAIL_TEMPLATE_UNKNOWN_FIELD_TYPE';
    public const INVALID_REQUEST_PARAMETER_TYPE = 'CONTENT__MAIL_TEMPLATE_INVALID_REQUEST_PARAMETER_TYPE';
    public const INVALID_SALES_CHANNEL_ID = 'CONTENT__MAIL_TEMPLATE_INVALID_SALES_CHANNEL_ID';

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
            Response::HTTP_NOT_FOUND,
            self::MAIL_TEMPLATE_NOT_FOUND,
            'Mail Template not found.'
        );
    }

    public static function missingDataProvider(string $entityName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAIL_TEMPLATE_MISSING_DATA_PROVIDER,
            'Missing mail data provider for entity "{{ entityName }}".',
            ['entityName' => $entityName],
        );
    }

    public static function invalidRequestParameterType(string $parameter, string $expectedType, string $actualType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_TYPE,
            'Invalid request parameter "{{ parameter }}". Expected type "{{ expectedType }}", got "{{ actualType }}".',
            [
                'parameter' => $parameter,
                'expectedType' => $expectedType,
                'actualType' => $actualType,
            ]
        );
    }

    public static function invalidSalesChannelId(string $salesChannelId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SALES_CHANNEL_ID,
            'Sales channel with id "{{ salesChannelId }}" was not found.',
            ['salesChannelId' => $salesChannelId],
        );
    }

    public static function unknownEventDataType(string $dataType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MAIL_TEMPLATE_UNKNOWN_EVENT_DATA_TYPE,
            'Unknown event data type: ' . $dataType,
        );
    }
}
