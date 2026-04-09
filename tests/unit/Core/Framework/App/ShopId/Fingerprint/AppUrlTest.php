<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(AppUrl::class)]
#[Package('framework')]
class AppUrlTest extends TestCase
{
    use EnvTestBehaviour;

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

        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        static::assertSame('https://example.com', $fingerprint->getStamp());

        $this->setEnvVars(['APP_URL' => 'https://foo.bar.com']);
        static::assertSame('https://foo.bar.com', $fingerprint->getStamp());
    }

    public function testThrowsIfAppUrlEnvVarIsNotSet(): void
    {
        $fingerprint = new AppUrl();

        $this->setEnvVars(['APP_URL' => null]);

        static::expectException(AppException::class);
        static::expectExceptionMessage('The environment variable "APP_URL" is not set. Please set it to the URL to your Admin API.');

        $fingerprint->getStamp();
    }
}
