<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId\Fingerprint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('compareProvider')]
    public function testCompare(string $storedStamp, string $newStamp, int $expectedScore): void
    {
        $fingerprint = new InstallationPath($newStamp);

        static::assertSame($expectedScore, $fingerprint->compare($storedStamp));
    }

    public static function compareProvider(): \Generator
    {
        yield 'only-last-segment-changed' => [
            'storedStamp' => '/var/www/releases/1',
            'newStamp' => '/var/www/releases/2',
            'expectedScore' => 25,
        ];

        yield 'diff-root-path' => [
            'storedStamp' => '/var/www/releases/1',
            'newStamp' => '/root/www/releases/2',
            'expectedScore' => 100,
        ];

        yield 'last-half-changed' => [
            'storedStamp' => '/var/www/releases/1',
            'newStamp' => '/var/www/deploys/2',
            'expectedScore' => 50,
        ];

        yield 'all-different' => [
            'storedStamp' => '/var/www/releases/1',
            'newStamp' => '/root/docs/deploys/2',
            'expectedScore' => 100,
        ];

        yield 'all different' => [
            'storedStamp' => '/var/www/releases/1',
            'newStamp' => '/1/2/3/4/5/6/7',
            'expectedScore' => 98,
        ];

        yield 'only 1 segment and different' => [
            'storedStamp' => '/1',
            'newStamp' => '/2',
            'expectedScore' => 100,
        ];
    }
}
