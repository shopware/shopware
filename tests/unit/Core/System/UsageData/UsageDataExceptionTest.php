<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(UsageDataException::class)]
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
            \sprintf('No user available in context source "%s"', SystemSource::class),
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
            \sprintf('Expected context source to be "%s" but got "%s".', AdminApiSource::class, SystemSource::class),
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

    public function testUnexpectedOperationInInitialRun(): void
    {
        $exception = UsageDataException::unexpectedOperationInInitialRun(Operation::DELETE);

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals('SYSTEM__USAGE_DATA_UNEXPECTED_OPERATION_IN_INITIAL_RUN', $exception->getErrorCode());
        static::assertEquals('Operation "delete" was not expected to be dispatched in initial run', $exception->getMessage());
    }

    public function testEntityNotAllowed(): void
    {
        $exception = UsageDataException::entityNotAllowed('product');

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals('SYSTEM__USAGE_DATA_ENTITY_NOT_TAGGED', $exception->getErrorCode());
        static::assertEquals('Entity "product" is not allowed to be used for usage data', $exception->getMessage());
    }

    public function testFailedToCompressEntityDispatchPayload(): void
    {
        $exception = UsageDataException::failedToCompressEntityDispatchPayload();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals('SYSTEM__USAGE_DATA_FAILED_TO_COMPRESS_ENTITY_DISPATCH_PAYLOAD', $exception->getErrorCode());
        static::assertEquals('Failed to compress entity dispatch payload', $exception->getMessage());
    }

    public function testFailedToLoadDefaultAllowList(): void
    {
        $exception = UsageDataException::failedToLoadDefaultAllowList();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals('SYSTEM__USAGE_DATA_FAILED_TO_LOAD_DEFAULT_ALLOW_LIST', $exception->getErrorCode());
        static::assertEquals('Failed to load default allow list', $exception->getMessage());
    }

    #[DisabledFeatures(['v6.7.0.0'])]
    public function testShopIdChanged(): void
    {
        $exception = UsageDataException::shopIdChanged();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals('SYSTEM__USAGE_DATA_SHOP_ID_CHANGED', $exception->getErrorCode());
        static::assertEquals('shopId changed', $exception->getMessage());
    }

    public function testShopIdChangedIsDeprecated(): void
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage(Feature::deprecatedMethodMessage(UsageDataException::class, 'shopIdChanged', '6.7.0.0'));

        UsageDataException::shopIdChanged();
    }
}
