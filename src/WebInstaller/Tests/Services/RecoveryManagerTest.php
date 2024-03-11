<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\RecoveryManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(RecoveryManager::class)]
class RecoveryManagerTest extends TestCase
{
    public function testGetBinary(): void
    {
        $recoveryManager = new RecoveryManager();

        static::assertSame($_SERVER['SCRIPT_FILENAME'], $recoveryManager->getBinary());
    }

    public function testGetProjectDir(): void
    {
        $recoveryManager = new RecoveryManager();

        $fileName = realpath($_SERVER['SCRIPT_FILENAME']);
        static::assertIsString($fileName);
        static::assertSame(\dirname($fileName), $recoveryManager->getProjectDir());
    }

    public function testGetShopwareLocationReturnsFalseMissingShopware(): void
    {
        $recoveryManager = new RecoveryManager();

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Could not find Shopware installation');
        $recoveryManager->getShopwareLocation();
    }

    #[BackupGlobals(true)]
    public function testGetShopwareLocationFailsDueNonPublicDirectory(): void
    {
        $recoveryManager = new RecoveryManager();

        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $_SERVER['SCRIPT_FILENAME'] = $tmpDir . '/foo/shopware-installer.phar.php';
        $fs->mkdir($tmpDir . '/foo');
        $fs->touch($tmpDir . '/foo/shopware-installer.phar.php');

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Could not find Shopware installation');
        $recoveryManager->getShopwareLocation();

        $fs->remove($tmpDir);
    }

    #[BackupGlobals(true)]
    public function testGetShopwareLocationReturnsShopwareLocation(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();
        $this->prepareShopware($fs, $tmpDir);

        static::assertSame(realpath($tmpDir), $recoveryManager->getShopwareLocation());

        $fs->remove($tmpDir);
    }

    #[BackupGlobals(true)]
    public function testGetShopwareVersion(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();
        $this->prepareShopware($fs, $tmpDir);

        static::assertSame('6.4.10.0', $recoveryManager->getCurrentShopwareVersion($tmpDir));

        $fs->remove($tmpDir);
    }

    #[BackupGlobals(true)]
    public function testGetShopwareVersionPrefixed(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();
        $this->prepareShopware($fs, $tmpDir, 'v6.4.10.0');

        static::assertSame('6.4.10.0', $recoveryManager->getCurrentShopwareVersion($tmpDir));

        $fs->remove($tmpDir);
    }

    public function testGetVersionEmptyFolder(): void
    {
        $recoveryManager = new RecoveryManager();

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Could not find composer.lock file');
        $recoveryManager->getCurrentShopwareVersion(__DIR__);
    }

    public function testGetVersionFromLock(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();

        $fs->mkdir($tmpDir);

        $fs->dumpFile($tmpDir . '/composer.lock', json_encode([
            'packages' => [],
        ], \JSON_THROW_ON_ERROR));

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Could not find Shopware in composer.lock file');
        $recoveryManager->getCurrentShopwareVersion($tmpDir);
    }

    public function testSymfonyLock(): void
    {
        $recoveryManager = new RecoveryManager();

        static::assertFalse($recoveryManager->isFlexProject(__DIR__));

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();

        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/symfony.lock', json_encode([], \JSON_THROW_ON_ERROR));

        static::assertTrue($recoveryManager->isFlexProject($tmpDir));
    }

    public function testGetPHPBinary(): void
    {
        $recoveryManager = new RecoveryManager();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set('phpBinary', 'php');

        static::assertSame('php', $recoveryManager->getPHPBinary($request));
    }

    private function prepareShopware(Filesystem $fs, string $tmpDir, string $version = '6.4.10.0'): void
    {
        $fs->mkdir($tmpDir);
        $fs->mkdir($tmpDir . '/public');

        $_SERVER['SCRIPT_FILENAME'] = $tmpDir . '/public/shopware-installer.phar.php';
        $fs->touch($_SERVER['SCRIPT_FILENAME']);

        $fs->dumpFile($tmpDir . '/composer.json', json_encode([
            'require' => [
                'shopware/core' => $version,
            ],
        ], \JSON_THROW_ON_ERROR));

        $fs->dumpFile($tmpDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'shopware/core',
                    'version' => $version,
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }
}
