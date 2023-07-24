<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class DataAbstractionLayerException extends HttpException
{
    public const INVALID_FIELD_SERIALIZER_CODE = 'FRAMEWORK__INVALID_FIELD_SERIALIZER';

    public const INVALID_CRON_INTERVAL_CODE = 'FRAMEWORK__INVALID_CRON_INTERVAL_FORMAT';

    public const INVALID_DATE_INTERVAL_CODE = 'FRAMEWORK__INVALID_DATE_INTERVAL_FORMAT';

    public static function invalidSerializerField(string $expectedClass, Field $field): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            new InvalidSerializerFieldException($expectedClass, $field);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_FIELD_SERIALIZER_CODE,
            'Expected field of type "{{ expectedField }}" got "{{ field }}".',
            ['expectedField' => $expectedClass, 'field' => $field::class]
        );
    }

    public static function invalidCronIntervalFormat(string $cronIntervalString): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CRON_INTERVAL_CODE,
            'Unknown or bad CronInterval format "{{ cronIntervalString }}".',
            ['cronIntervalString' => $cronIntervalString],
        );
    }

    public static function invalidDateIntervalFormat(
        string $dateIntervalString,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_DATE_INTERVAL_CODE,
            'Unknown or bad DateInterval format "{{ dateIntervalString }}".',
            ['dateIntervalString' => $dateIntervalString],
            $previous,
        );
    }
}
