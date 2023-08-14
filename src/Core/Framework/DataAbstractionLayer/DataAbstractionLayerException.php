<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class DataAbstractionLayerException extends HttpException
{
    public const INVALID_FIELD_SERIALIZER_CODE = 'FRAMEWORK__INVALID_FIELD_SERIALIZER';

    public const INVALID_CRON_INTERVAL_CODE = 'FRAMEWORK__INVALID_CRON_INTERVAL_FORMAT';

    public const INVALID_DATE_INTERVAL_CODE = 'FRAMEWORK__INVALID_DATE_INTERVAL_FORMAT';

    public const INVALID_CRITERIA_IDS = 'FRAMEWORK__INVALID_CRITERIA_IDS';

    public const INVALID_API_CRITERIA_IDS = 'FRAMEWORK__INVALID_API_CRITERIA_IDS';

    final public const INVALID_LANGUAGE_ID = 'FRAMEWORK__INVALID_LANGUAGE_ID';

    public static function invalidSerializerField(string $expectedClass, Field $field): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new InvalidSerializerFieldException($expectedClass, $field);
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

    /**
     * @param array<mixed> $ids
     */
    public static function invalidCriteriaIds(array $ids, string $reason): self
    {
        return new InvalidCriteriaIdsException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_CRITERIA_IDS,
            'Invalid ids provided in criteria. {{ reason }}. Ids: {{ ids }}.',
            ['ids' => print_r($ids, true), 'reason' => $reason]
        );
    }

    public static function invalidApiCriteriaIds(self $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_API_CRITERIA_IDS,
            $previous->getMessage(),
            $previous->getParameters(),
        );
    }

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - will return `self` in the future
     */
    public static function invalidLanguageId(?string $languageId): HttpException
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new LanguageNotFoundException($languageId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_LANGUAGE_ID,
            'The provided language id "{{ languageId }}" is invalid.',
            ['languageId' => $languageId]
        );
    }
}
