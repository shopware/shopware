<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\System\UsageData\UsageDataException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\UsageDataException
 */
#[Package('merchant-services')]
class UsageDataExceptionTest extends TestCase
{
    public function testMissingUserInContextSource(): void
    {
        $exception = UsageDataException::missingUserInContextSource(SystemSource::class);

        static::assertSame(
            UsageDataException::MISSING_USER_IN_CONTEXT_SOURCE,
            $exception->getErrorCode()
        );
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getStatusCode()
        );
        static::assertSame(
            sprintf('No user available in context source "%s"', SystemSource::class),
            $exception->getMessage(),
        );
    }

    public function testInvalidContextSource(): void
    {
        $exception = UsageDataException::invalidContextSource(
            AdminApiSource::class,
            SystemSource::class,
        );

        static::assertSame(
            UsageDataException::INVALID_CONTEXT_SOURCE,
            $exception->getErrorCode()
        );
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getStatusCode()
        );
        static::assertSame(
            sprintf('Expected context source to be "%s" but got "%s".', AdminApiSource::class, SystemSource::class),
            $exception->getMessage(),
        );
    }

    public function testConsentAlreadyRequested(): void
    {
        $exception = UsageDataException::consentAlreadyRequested();

        static::assertInstanceOf(
            ConsentAlreadyRequestedException::class,
            $exception
        );
        static::assertSame(
            UsageDataException::CONSENT_ALREADY_REQUESTED,
            $exception->getErrorCode()
        );
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getStatusCode()
        );
        static::assertSame(
            'Consent has already been requested.',
            $exception->getMessage(),
        );
    }

    public function testConsentAlreadyAccepted(): void
    {
        $exception = UsageDataException::consentAlreadyAccepted();

        static::assertInstanceOf(
            ConsentAlreadyAcceptedException::class,
            $exception
        );
        static::assertSame(
            UsageDataException::CONSENT_ALREADY_ACCEPTED,
            $exception->getErrorCode()
        );
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getStatusCode()
        );
        static::assertSame(
            'Consent has already been accepted.',
            $exception->getMessage(),
        );
    }

    public function testConsentAlreadyRevoked(): void
    {
        $exception = UsageDataException::consentAlreadyRevoked();

        static::assertInstanceOf(
            ConsentAlreadyRevokedException::class,
            $exception
        );
        static::assertSame(
            UsageDataException::CONSENT_ALREADY_REVOKED,
            $exception->getErrorCode()
        );
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getStatusCode()
        );
        static::assertSame(
            'Consent has already been revoked.',
            $exception->getMessage(),
        );
    }
}
