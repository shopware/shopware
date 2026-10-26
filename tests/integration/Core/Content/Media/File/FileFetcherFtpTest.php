<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileService;
use Shopware\Core\Content\Media\File\FileUrlValidator;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('discovery')]
class FileFetcherFtpTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/shopware-logo.png';

    private ?Process $server = null;

    private string $targetFile;

    protected function setUp(): void
    {
        $this->targetFile = (new Filesystem())->tempnam(sys_get_temp_dir(), 'ftp');
    }

    protected function tearDown(): void
    {
        $this->server?->stop(0);
        (new Filesystem())->remove($this->targetFile);
    }

    public function testDownloadsFromFtpServer(): void
    {
        $port = $this->startServer(pasvAddress: '127.0.0.1', epsv: true);

        $media = $this->createFetcher(resolvedIp: '127.0.0.1')
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/shopware-logo.png', $this->targetFile);

        static::assertSame('image/png', $media->getMimeType());
        static::assertFileEquals(self::FIXTURE, $this->targetFile);
    }

    public function testIgnoresPrivateAddressInPasvReply(): void
    {
        $port = $this->startServer(pasvAddress: '10.255.255.1', epsv: false);

        $this->createFetcher(resolvedIp: '127.0.0.1')
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/shopware-logo.png', $this->targetFile);

        static::assertFileEquals(self::FIXTURE, $this->targetFile);
    }

    public function testRejectsHostResolvingToPrivateAddress(): void
    {
        $port = $this->startServer(pasvAddress: '127.0.0.1', epsv: true);
        $url = 'ftp://ftp.example.com:' . $port . '/shopware-logo.png';

        $this->expectExceptionObject(MediaException::illegalUrl($url));

        $this->createFetcher(resolvedIp: '127.0.0.1', allowedPrivateIps: [])->fetchFromURL($url, $this->targetFile);
    }

    public function testRejectsFilesExceedingTheSizeLimit(): void
    {
        $port = $this->startServer(pasvAddress: '127.0.0.1', epsv: true);

        $this->expectExceptionObject(MediaException::fileSizeLimitExceeded());

        $this->createFetcher(resolvedIp: '127.0.0.1', maxFileSize: 100)
            ->fetchFromURL('ftp://ftp.example.com:' . $port . '/shopware-logo.png', $this->targetFile);
    }

    public function testAbortsStalledTransfer(): void
    {
        $port = $this->startServer(pasvAddress: '127.0.0.1', epsv: true, stall: true);
        $url = 'ftp://ftp.example.com:' . $port . '/shopware-logo.png';
        $socketTimeout = (string) \ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '1');

        $start = microtime(true);

        try {
            $this->createFetcher(resolvedIp: '127.0.0.1')->fetchFromURL($url, $this->targetFile);
            static::fail('The stalled transfer should be aborted.');
        } catch (MediaException $exception) {
            static::assertEquals(MediaException::cannotOpenSourceStreamToRead($url), $exception);
        } finally {
            ini_set('default_socket_timeout', $socketTimeout);
        }

        static::assertLessThan(10, microtime(true) - $start);
    }

    public function testIgnoresProxyFromEnvironment(): void
    {
        $port = $this->startServer(pasvAddress: '127.0.0.1', epsv: true);
        putenv('all_proxy=http://127.0.0.1:9');

        try {
            $this->createFetcher(resolvedIp: '127.0.0.1')
                ->fetchFromURL('ftp://ftp.example.com:' . $port . '/shopware-logo.png', $this->targetFile);
        } finally {
            putenv('all_proxy');
        }

        static::assertFileEquals(self::FIXTURE, $this->targetFile);
    }

    public function testSftpConnectsToTheCheckedAddress(): void
    {
        $this->server = new Process([
            (string) (new PhpExecutableFinder())->find(),
            '-r',
            '$s = stream_socket_server("tcp://127.0.0.1:0"); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1), PHP_EOL; echo stream_socket_accept($s, 10) ? "connected" : "timeout";',
        ]);
        $this->server->start();
        $this->server->waitUntil(static fn (string $type, string $output): bool => str_contains($output, \PHP_EOL));
        $url = 'sftp://user:secret@sftp.example.com:' . trim($this->server->getOutput()) . '/shopware-logo.png';

        try {
            $this->createFetcher(resolvedIp: '127.0.0.1')->fetchFromURL($url, $this->targetFile);
            static::fail('The download from a server without SSH should fail.');
        } catch (MediaException $exception) {
            static::assertEquals(MediaException::cannotOpenSourceStreamToRead($url), $exception);
        }

        $this->server->wait();
        static::assertStringEndsWith('connected', $this->server->getOutput());
    }

    private function startServer(string $pasvAddress, bool $epsv, bool $stall = false): int
    {
        $this->server = new Process([
            (string) (new PhpExecutableFinder())->find(),
            __DIR__ . '/../fixtures/ftp-server.php',
            self::FIXTURE,
            $pasvAddress,
            $epsv ? '1' : '0',
            $stall ? '1' : '0',
        ]);
        $this->server->start();
        $this->server->waitUntil(static fn (string $type, string $output): bool => str_contains($output, \PHP_EOL));

        return (int) trim($this->server->getOutput());
    }

    /**
     * @param list<string> $allowedPrivateIps
     */
    private function createFetcher(string $resolvedIp, array $allowedPrivateIps = ['127.0.0.1'], int $maxFileSize = 0): FileFetcher
    {
        $resolver = new TrustedUrlResolver(
            static fn (string $host): array => [$resolvedIp],
            allowedPrivateIps: $allowedPrivateIps,
            allowedSchemes: ['ftp', 'sftp'],
        );

        return new FileFetcher(
            new FileUrlValidator($resolver),
            new FileService(['ftp', 'sftp']),
            $resolver,
            new MockHttpClient(),
            maxFileSize: $maxFileSize,
        );
    }
}
