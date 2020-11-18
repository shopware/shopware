<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\Download;

/**
 * @group needsWebserver
 */
class DownloadTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var int|null
     */
    private $errorMask;

    /**
     * @var string[]
     */
    private $testFiles = [];

    protected function tearDown(): void
    {
        if ($this->errorMask !== null) {
            error_reporting($this->errorMask);
        }

        foreach ($this->testFiles as $testFile) {
            unlink($testFile);
            @unlink($testFile . '.part');
        }
    }

    public function testDownloadFile(): void
    {
        $download = new Download();

        $tempfile = $this->tmpFile('/tmp', 'updateFile');

        $download->downloadFile(
            'http://assets.shopware.com/sw_logo_white.png',
            $tempfile,
            10521,
            '5f98432a760cae72c85b1835017306bdd84e2f68'
        );

        static::assertFileExists($tempfile);
        static::assertEquals(filesize(__DIR__ . '/../_fixtures/sw_logo_white.png'), filesize($tempfile));
    }

    /**
     * @group slow
     */
    public function testExpectExceptionOnInvalidUrl(): void
    {
        $download = new Download();

        $this->expectExceptionMessage('Wrong http code');
        $download->downloadFile($_SERVER['APP_URL'] . '/foobar', $this->tmpFile(), 1, '1234');
    }

    public function testUnicode(): void
    {
        $tempfile = $this->tmpFile('/tmp', 'updateFile');
        $remoteFile = $this->createPublicTestFile('/foo.bin', '💣💣💣 Bomb');

        $sha1 = sha1_file($remoteFile);
        $size = filesize($remoteFile);

        $download = new Download();

        $total = $download->downloadFile(
            $_SERVER['APP_URL'] . '/foo.bin',
            $tempfile,
            $size,
            $sha1
        );

        static::assertSame($size, $total);
        static::assertFileIsReadable($tempfile);
        static::assertSame($sha1, sha1_file($tempfile));
    }

    public function testInvalidHash(): void
    {
        $tempfile = $this->tmpFile('/tmp', 'updateFile');
        $remoteFile = $this->createPublicTestFile('/foo.bin', 'Test');

        $size = filesize($remoteFile);

        $download = new Download();

        $this->expectException(UpdateFailedException::class);
        $this->expectExceptionMessage('Hash mismatch');

        $download->downloadFile(
            $_SERVER['APP_URL'] . '/foo.bin',
            $tempfile,
            $size,
            'invalid'
        );
    }

    /**
     * @group slow
     */
    public function testDownload100MiB(): void
    {
        $size = 1024 * 1024 * 100;
        $tempfile = $this->tmpFile('/tmp', 'updateFile');

        $remoteFile = $this->createPublicTestFile('/foo.bin', null, $size);

        $sha1 = sha1_file($remoteFile);

        $download = new Download();

        $callback = $this->getMockBuilder('stdClass')
            ->addMethods(['callback'])
            ->getMock();

        $totalDownloaded = 0;

        $callback->expects(static::atLeastOnce())
            ->method('callback')
            ->willReturnCallback(function ($totalSize, $downloaded, $total) use ($size, &$totalDownloaded): void {
                $this->assertSame($size, $totalSize);
                $this->assertLessThanOrEqual($size, $downloaded);
                $this->assertLessThanOrEqual($size, $total);

                $totalDownloaded = $downloaded;
            });

        $download->setProgressCallback([$callback, 'callback']);

        $total = $download->downloadFile(
            $_SERVER['APP_URL'] . '/foo.bin',
            $tempfile,
            $size,
            $sha1
        );

        static::assertSame($size, $totalDownloaded);
        static::assertSame($size, $total);
        static::assertFileIsReadable($tempfile);
        static::assertSame($sha1, sha1_file($tempfile));
    }

    public function testExpectUpdateFailedToExistingFile(): void
    {
        $this->expectException(UpdateFailedException::class);

        $tempfile = $this->tmpFile();
        $this->expectExceptionMessage('File on destination ' . $tempfile . ' does already exist.');

        \file_put_contents($tempfile, 'x');

        $download = new Download();
        $download->downloadFile('asdf', $tempfile, 1, 'asdf');
    }

    public function testExpectUpdateFailedToExistingFile2(): void
    {
        $this->expectException(UpdateFailedException::class);
        $this->expectExceptionMessage('Destination "unknown://foobar" is invalid.');

        $this->errorMask = error_reporting(0);
        $download = new Download();
        $download->downloadFile('asdf', 'unknown://foobar', 1, 'asdf');
    }

    public function testHaltCallbackIsExecutedAndCalledOnce(): void
    {
        $size = 1024 * 1024 * 5;
        $tempfile = $this->tmpFile();

        $this->createPublicTestFile('/foo.bin', null, $size);

        $called = 0;
        $download = new Download();
        $download->setHaltCallback(static function () use (&$called) {
            ++$called;

            return true;
        });

        $actual = $download->downloadFile(
            $_SERVER['APP_URL'] . '/foo.bin',
            $tempfile,
            $size,
            'sadffasdjkljsadfk'
        );

        static::assertLessThan($size, $actual);
        static::assertSame(1, $called);
    }

    private function tmpFile(): string
    {
        $tempfile = tempnam('/tmp', 'updateFile');

        $this->testFiles[] = $tempfile;

        return $tempfile;
    }

    private function createPublicTestFile(string $path, ?string $content = null, ?int $size = null): string
    {
        $projectDir = $this->getContainer()->get('kernel')->getProjectDir();

        $dir = $projectDir . '/public' . dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir);
        }

        $testFile = $projectDir . '/public' . $path;
        $handle = fopen($testFile, 'wb');

        if ($content !== null) {
            fwrite($handle, $content);
        } else {
            $size = $size ?? 1024;
            ftruncate($handle, $size);
        }

        fclose($handle);

        $this->testFiles[] = $testFile;

        return $testFile;
    }
}
