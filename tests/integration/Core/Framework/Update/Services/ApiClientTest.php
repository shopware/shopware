<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Update\Services;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[Package('framework')]
class ApiClientTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('api-client-test-', true);
        $this->filesystem->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testDownloadRecoveryTool(): void
    {
        $responses = [
            new MockResponse('test', ['Content-Type' => 'application/json']),
        ];

        $httpClient = new MockHttpClient($responses);
        $client = new ApiClient($httpClient, '6.4.0.0', $this->projectDir, new NativeClock());

        $client->downloadRecoveryTool();

        static::assertStringEqualsFile($this->projectDir . '/public/shopware-installer.phar.php', 'test');
        static::assertSame(1, $httpClient->getRequestsCount());
    }
}
