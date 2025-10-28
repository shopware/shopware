<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(InstallationPath::class)]
#[Package('framework')]
class InstallationPathTest extends TestCase
{
    public function testIdentifier(): void
    {
        $fingerprint = new InstallationPath('/var/www/html');

        static::assertSame('installation_path', $fingerprint->getIdentifier());
    }

    public function testScore(): void
    {
        $fingerprint = new InstallationPath('/var/www/html');

        static::assertSame(100, $fingerprint->getScore());
    }

    public function testTakesInstallationPath(): void
    {
        $fingerprint = new InstallationPath('/var/www/html');

        static::assertSame('/var/www/html', $fingerprint->getStamp());
    }
}
