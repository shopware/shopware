<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieException::class)]
#[DisabledFeatures(['v6.9.0.0'])]
class CookieExceptionTest extends TestCase
{
    use EnvTestBehaviour;

    public function testInvalidLegacyCookieGroupProvided(): void
    {
        $exception = Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided(['isRequired' => true]));

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_GROUP_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie group provided: {"isRequired":true}. The key "snippet_name" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testInvalidLegacyCookieEntryProvided(): void
    {
        $exception = Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided(['value' => '1']));

        static::assertSame('CONTENT__COOKIE_INVALID_LEGACY_COOKIE_ENTRY_PROVIDED', $exception->getErrorCode());
        static::assertSame('Invalid legacy cookie entry provided: {"value":"1"}. The key "cookie" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testInvalidLegacyCookieGroupWithInvalidEncoding(): void
    {
        $exception = Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieGroupProvided(['snippet_name' => "\xB1\x31"]));

        static::assertSame('Invalid legacy cookie group provided: Could not encode cookie group to JSON. The key "snippet_name" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testInvalidLegacyCookieEntryWithInvalidEncoding(): void
    {
        $exception = Feature::silent('v6.9.0.0', static fn () => CookieException::invalidLegacyCookieEntryProvided(['cookie' => "\xB1\x31"]));

        static::assertSame('Invalid legacy cookie entry provided: Could not encode cookie entry to JSON. The key "cookie" is required.', $exception->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the legacy exception factory.
     */
    public function testInvalidLegacyCookieGroupThrowsWithThe69FlagActive(): void
    {
        Feature::registerFeature('v6.8.0.0', ['default' => true]);
        Feature::registerFeature('v6.9.0.0', ['default' => false]);
        $this->setEnvVars(['V6_9_0_0' => '1']);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieGroupProvided', 'v6.9.0.0')));

        CookieException::invalidLegacyCookieGroupProvided([]);
    }

    /**
     * @deprecated tag:v6.9.0 - Remove with the legacy exception factory.
     */
    public function testInvalidLegacyCookieEntryThrowsWithThe69FlagActive(): void
    {
        Feature::registerFeature('v6.8.0.0', ['default' => true]);
        Feature::registerFeature('v6.9.0.0', ['default' => false]);
        $this->setEnvVars(['V6_9_0_0' => '1']);
        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(CookieException::class, CookieException::class . '::invalidLegacyCookieEntryProvided', 'v6.9.0.0')));

        CookieException::invalidLegacyCookieEntryProvided([]);
    }
}
