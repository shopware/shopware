<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData;

use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\System\UsageData\Exception\ShopIdChangedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('data-services')]
class UsageDataException extends HttpException
{
    public const MISSING_USER_IN_CONTEXT_SOURCE = 'SYSTEM__USAGE_DATA_MISSING_USER_IN_CONTEXT_SOURCE';
    public const INVALID_CONTEXT_SOURCE = 'SYSTEM__USAGE_DATA_INVALID_CONTEXT_SOURCE';
    public const CONSENT_ALREADY_REQUESTED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_REQUESTED';
    public const CONSENT_ALREADY_ACCEPTED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_ACCEPTED';
    public const CONSENT_ALREADY_REVOKED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_REVOKED';
    public const UNEXPECTED_OPERATION_IN_INITIAL_RUN = 'SYSTEM__USAGE_DATA_UNEXPECTED_OPERATION_IN_INITIAL_RUN';
    public const ENTITY_NOT_TAGGED = 'SYSTEM__USAGE_DATA_ENTITY_NOT_TAGGED';
    public const SYSTEM__USAGE_DATA_FAILED_TO_COMPRESS_ENTITY_DISPATCH_PAYLOAD = 'SYSTEM__USAGE_DATA_FAILED_TO_COMPRESS_ENTITY_DISPATCH_PAYLOAD';
    public const SYSTEM__USAGE_DATA_FAILED_TO_LOAD_DEFAULT_ALLOW_LIST = 'SYSTEM__USAGE_DATA_FAILED_TO_LOAD_DEFAULT_ALLOW_LIST';
    public const SYSTEM__USAGE_DATA_SHOP_ID_CHANGED = 'SYSTEM__USAGE_DATA_SHOP_ID_CHANGED';

    /**
     * @param class-string<ContextSource> $contextSource
     */
    public static function missingUserInContextSource(
        string $contextSource,
        ?\Throwable $previous = null
    ): self {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_USER_IN_CONTEXT_SOURCE,
            'No user available in context source "{{ contextSource }}"',
            ['contextSource' => $contextSource],
            $previous,
        );
    }

    /**
     * @param class-string<ContextSource> $expectedContextSource
     * @param class-string<ContextSource> $actualContextSource
     */
    public static function invalidContextSource(string $expectedContextSource, string $actualContextSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_CONTEXT_SOURCE,
            'Expected context source to be "{{ expectedContextSource }}" but got "{{ actualContextSource }}".',
            [
                'expectedContextSource' => $expectedContextSource,
                'actualContextSource' => $actualContextSource,
            ],
        );
    }

    public static function consentAlreadyRequested(): self
    {
        return new ConsentAlreadyRequestedException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONSENT_ALREADY_REQUESTED,
            'Consent has already been requested.',
        );
    }

    public static function consentAlreadyAccepted(): self
    {
        return new ConsentAlreadyAcceptedException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONSENT_ALREADY_ACCEPTED,
            'Consent has already been accepted.',
        );
    }

    public static function consentAlreadyRevoked(): self
    {
        return new ConsentAlreadyRevokedException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONSENT_ALREADY_REVOKED,
            'Consent has already been revoked.',
        );
    }

    public static function unexpectedOperationInInitialRun(Operation $operation): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNEXPECTED_OPERATION_IN_INITIAL_RUN,
            'Operation "{{ operation }}" was not expected to be dispatched in initial run',
            ['operation' => $operation->value],
        );
    }

    public static function entityNotAllowed(string $entityName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ENTITY_NOT_TAGGED,
            'Entity "{{ entityName }}" is not allowed to be used for usage data',
            ['entityName' => $entityName],
        );
    }

    public static function failedToCompressEntityDispatchPayload(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SYSTEM__USAGE_DATA_FAILED_TO_COMPRESS_ENTITY_DISPATCH_PAYLOAD,
            'Failed to compress entity dispatch payload',
        );
    }

    public static function failedToLoadDefaultAllowList(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SYSTEM__USAGE_DATA_FAILED_TO_LOAD_DEFAULT_ALLOW_LIST,
            'Failed to load default allow list',
        );
    }

    /**
     * @deprecated tag:v6.7.0 will be removed with no replacement
     */
    public static function shopIdChanged(): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __FUNCTION__, '6.7.0.0')
        );

        return new ShopIdChangedException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SYSTEM__USAGE_DATA_SHOP_ID_CHANGED,
            'shopId changed'
        );
    }
}
