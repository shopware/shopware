<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData;

use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('merchant-services')]
class UsageDataException extends HttpException
{
    public const MISSING_USER_IN_CONTEXT_SOURCE = 'SYSTEM__USAGE_DATA_MISSING_USER_IN_CONTEXT_SOURCE';
    public const INVALID_CONTEXT_SOURCE = 'SYSTEM__USAGE_DATA_INVALID_CONTEXT_SOURCE';
    public const CONSENT_ALREADY_REQUESTED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_REQUESTED';
    public const CONSENT_ALREADY_ACCEPTED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_ACCEPTED';
    public const CONSENT_ALREADY_REVOKED = 'SYSTEM__USAGE_DATA_CONSENT_ALREADY_REVOKED';

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
}
