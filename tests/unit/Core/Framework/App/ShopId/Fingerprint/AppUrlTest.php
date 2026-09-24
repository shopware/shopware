<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestEnvironment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUrl::class)]
class AppUrlTest extends TestCase
{
    public function testIdentifier(): void
    {
        $fingerprint = new AppUrl();

        static::assertSame('app_url', $fingerprint->getIdentifier());
    }

    public function testScore(): void
    {
        $fingerprint = new AppUrl();

        static::assertSame(100, $fingerprint->getScore());
    }

    public function testTakesAppUrlFromEnv(): void
    {
        $fingerprint = new AppUrl();

        TestEnvironment::set(['APP_URL' => 'https://example.com']);
        static::assertSame('https://example.com', $fingerprint->getStamp());

        TestEnvironment::set(['APP_URL' => 'https://foo.bar.com']);
        static::assertSame('https://foo.bar.com', $fingerprint->getStamp());
    }

    public function testThrowsIfAppUrlEnvVarIsNotSet(): void
    {
        $fingerprint = new AppUrl();

        TestEnvironment::set(['APP_URL' => null]);

        $this->expectExceptionObject(AppException::appUrlNotConfigured());

        $fingerprint->getStamp();
    }
}
