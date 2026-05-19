<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentException::class)]
class ConsentExceptionTest extends TestCase
{
    public function testNotFound(): void
    {
        $e = ConsentException::notFound('test-consent');

        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame(ConsentException::NOT_FOUND, $e->getErrorCode());
        static::assertSame('Consent with name "test-consent" not found.', $e->getMessage());
        static::assertSame(['name' => 'test-consent'], $e->getParameters());
    }

    public function testInvalidStorage(): void
    {
        $options = ['storage1', 'storage2', 'storage3'];
        $e = ConsentException::invalidStorage('invalid-storage', $options);

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ConsentException::STORAGE_NOT_FOUND, $e->getErrorCode());
        static::assertSame('Consent storage "invalid-storage" not found. Available stores: storage1, storage2, storage3.', $e->getMessage());
        static::assertSame([
            'storage' => 'invalid-storage',
            'options' => 'storage1, storage2, storage3',
        ], $e->getParameters());
    }

    public function testInvalidConsent(): void
    {
        $e = ConsentException::invalidConsent();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ConsentException::INVALID_CONSENT, $e->getErrorCode());
        static::assertSame('Consent is invalid.', $e->getMessage());
        static::assertSame([], $e->getParameters());
    }

    public function testInvalidConsentStatus(): void
    {
        $e = ConsentException::invalidConsentStatus();

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ConsentException::INVALID_CONSENT_STATUS, $e->getErrorCode());
        static::assertSame('Consent status is invalid.', $e->getMessage());
        static::assertSame([], $e->getParameters());
    }

    public function testInvalidScope(): void
    {
        $e = ConsentException::invalidScope('invalid-scope');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ConsentException::INVALID_SCOPE, $e->getErrorCode());
        static::assertSame('No scope found with name "invalid-scope".', $e->getMessage());
        static::assertSame(['scope' => 'invalid-scope'], $e->getParameters());
    }

    public function testInvalidRevision(): void
    {
        $e = ConsentException::invalidRevision('product_analytics', '1.0.0', '2.0.0');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ConsentException::INVALID_REVISION, $e->getErrorCode());
        static::assertSame('Cannot accept consent "product_analytics" for revision "1.0.0". The latest revision is "2.0.0".', $e->getMessage());
        static::assertSame([
            'consent' => 'product_analytics',
            'providedRevision' => '1.0.0',
            'latestRevision' => '2.0.0',
        ], $e->getParameters());
    }
}
