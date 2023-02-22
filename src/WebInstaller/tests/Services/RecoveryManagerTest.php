<?php
declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\RecoveryManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 *
 * @covers \App\Services\RecoveryManager
 */
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

        $fileName = $_SERVER['SCRIPT_FILENAME'];
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

    /**
     * @backupGlobals enabled
     */
    public function testGetShopwareLocationReturnsShopwareLocation(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();
        $this->prepareShopware($fs, $tmpDir);

        static::assertSame($tmpDir, $recoveryManager->getShopwareLocation());

        $fs->remove($tmpDir);
    }

    /**
     * @backupGlobals enabled
     */
    public function testGetShopwareVersion(): void
    {
        $recoveryManager = new RecoveryManager();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);

        $fs = new Filesystem();
        $this->prepareShopware($fs, $tmpDir);

        static::assertSame('6.4.10.0', $recoveryManager->getCurrentShopwareVersion($tmpDir));

        $fs->remove($tmpDir);
    }

    public function testGetVersionEmptyFolder(): void
    {
        $recoveryManager = new RecoveryManager();

        static::assertSame('unknown', $recoveryManager->getCurrentShopwareVersion(__DIR__));
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

        static::assertSame('unknown', $recoveryManager->getCurrentShopwareVersion($tmpDir));
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

    private function prepareShopware(Filesystem $fs, string $tmpDir): void
    {
        $fs->mkdir($tmpDir);

        $_SERVER['SCRIPT_FILENAME'] = $tmpDir . '/shopware-installer.phar.php';

        $fs->dumpFile($tmpDir . '/composer.json', json_encode([
            'require' => [
                'shopware/core' => '6.4.10.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $fs->dumpFile($tmpDir . '/composer.lock', json_encode([
            'packages' => [
                [
                    'name' => 'shopware/core',
                    'version' => '6.4.10.0',
                ],
            ],
        ], \JSON_THROW_ON_ERROR));
    }
}
