<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Feature\Triggerer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieException::class)]
class CookieExceptionTest extends TestCase
{
    use EnvTestBehaviour;

    private ?Triggerer $triggererBackup;

    protected function setUp(): void
    {
        $this->triggererBackup = Feature::$triggerer;
    }

    protected function tearDown(): void
    {
        Feature::$triggerer = $this->triggererBackup;
        parent::tearDown();
    }

    #[TestDox('invalid legacy cookie groups are JSON-encoded into the message')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidLegacyCookieGroupProvided(): void
    {
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())->method('deprecation')
            ->with('', '', Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieGroupProvided', 'v6.8.0.0'));
        Feature::$triggerer = $triggerer;

        $exception = CookieException::invalidLegacyCookieGroupProvided(['isRequired' => true]);

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_GROUP_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie group provided: {"isRequired":true}. The key "snippet_name" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    #[TestDox('invalid legacy cookie entries are JSON-encoded into the message')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidLegacyCookieEntryProvided(): void
    {
        $this->setEnvVars(['TESTS_RUNNING' => false]);
        $triggerer = $this->createMock(Triggerer::class);
        $triggerer->expects($this->once())->method('deprecation')
            ->with('', '', Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieEntryProvided', 'v6.8.0.0'));
        Feature::$triggerer = $triggerer;

        $exception = CookieException::invalidLegacyCookieEntryProvided(['value' => '1']);

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_ENTRY_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie entry provided: {"value":"1"}. The key "cookie" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyCookieGroupFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieGroupProvided', 'v6.8.0.0')));

        CookieException::invalidLegacyCookieGroupProvided([]);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyCookieEntryFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieEntryProvided', 'v6.8.0.0')));

        CookieException::invalidLegacyCookieEntryProvided([]);
    }

    public function testConsentLogStorageNotFound(): void
    {
        $exception = CookieException::consentLogStorageNotFound('s3', ['database', 'none']);

        static::assertSame('CONTENT__COOKIE_CONSENT_LOG_STORAGE_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The cookie consent log storage "s3" is not available. Available storages are: "database", "none".', $exception->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testInvalidConsentId(): void
    {
        $exception = CookieException::invalidConsentId('../etc/passwd');

        static::assertSame('CONTENT__COOKIE_INVALID_CONSENT_ID', $exception->getErrorCode());
        static::assertSame('The cookie consent id "../etc/passwd" does not match the pattern /^[A-Za-z0-9_-]{1,64}$/D', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
