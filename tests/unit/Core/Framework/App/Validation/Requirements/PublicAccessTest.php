<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation\Requirements;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\Requirements\PublicAccess;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * @internal
 */
#[CoversClass(PublicAccess::class)]
class PublicAccessTest extends TestCase
{
    use EnvTestBehaviour;

    private PublicAccess $requirement;

    private MockHandler $mockHandler;

    private Client $guzzle;

    private SecureUrlValidator $secureUrlValidator;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->guzzle = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->secureUrlValidator = new SecureUrlValidator(static fn (string $host): array => [['ip' => '8.8.8.8']]);
        $this->requirement = new PublicAccess($this->secureUrlValidator, $this->guzzle);
    }

    public function testValidateReturnsUnmetRequirementWhenAppUrlNotSet(): void
    {
        $this->setEnvVars(['APP_URL' => null]);
        $manifest = $this->createManifestMock();

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame('test-app', $result->appName);
        static::assertSame('public-access', $result->requirementName);
        static::assertSame(
            'The APP_URL environment variable is not configured.',
            $result->actionableResolution
        );
    }

    public function testValidateReturnsUnmetRequirementWhenUrlNotValid(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://localhost']);
        $manifest = $this->createManifestMock();

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame(
            'APP_URL "https://localhost" is not a valid public URL. It must use HTTPS, must not be an IP address, must not use a reserved domain, and its host must resolve via DNS to a public IP address.',
            $result->actionableResolution
        );
    }

    public function testValidateReturnsNullWhenHealthCheckReturns200(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));

        $result = $this->requirement->validate($manifest);

        static::assertNull($result);
    }

    public function testValidateReturnsUnmetRequirementWhenHealthCheckReturnsNon200(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        $this->mockHandler->append(new Response(HttpResponse::HTTP_INTERNAL_SERVER_ERROR));

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame(
            'Health check at "https://shopware.com/api/_info/health-check" returned HTTP 500. Ensure the Shopware instance is running and publicly reachable.',
            $result->actionableResolution
        );
    }

    public function testValidateReturnsHttpStatusWhenRequestExceptionHasResponse(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        $request = new Request('GET', 'https://shopware.com/api/_info/health-check');
        $response = new Response(HttpResponse::HTTP_SERVICE_UNAVAILABLE);
        $this->mockHandler->append(new RequestException('Server error', $request, $response));

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame(
            'Health check at "https://shopware.com/api/_info/health-check" returned HTTP 503. Ensure the Shopware instance is running and publicly reachable.',
            $result->actionableResolution
        );
    }

    public function testValidateReturnsUnreachableWhenRequestExceptionHasNoResponse(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        $this->mockHandler->append(new RequestException('Request failed', new Request('GET', 'test')));

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame(
            'Could not reach "https://shopware.com/api/_info/health-check". Ensure the Shopware instance is publicly accessible at the configured APP_URL.',
            $result->actionableResolution
        );
    }

    public function testValidateReturnsUnreachableWhenConnectionFails(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        $this->mockHandler->append(new ConnectException('Connection refused', new Request('GET', 'test')));

        $result = $this->requirement->validate($manifest);

        static::assertNotNull($result);
        static::assertSame(
            'Could not reach "https://shopware.com/api/_info/health-check". Ensure the Shopware instance is publicly accessible at the configured APP_URL.',
            $result->actionableResolution
        );
    }

    public function testResultIsCached(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        // Only one response should be consumed due to caching
        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));

        // Call twice to verify caching - second call should use cached result
        $result1 = $this->requirement->validate($manifest);
        $result2 = $this->requirement->validate($manifest);

        static::assertNull($result1);
        static::assertNull($result2);

        // Verify only one HTTP request was made (due to caching)
        static::assertCount(0, $this->mockHandler);
    }

    public function testResetClearsCachedResult(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://shopware.com']);
        $manifest = $this->createManifestMock();

        // First response: success
        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));
        // Second response: failure (to prove cache was cleared)
        $this->mockHandler->append(new Response(HttpResponse::HTTP_INTERNAL_SERVER_ERROR));

        $result1 = $this->requirement->validate($manifest);
        static::assertNull($result1);

        $this->requirement->reset();

        $result2 = $this->requirement->validate($manifest);
        static::assertNotNull($result2);

        static::assertCount(0, $this->mockHandler);
    }

    private function createManifestMock(string $appName = 'test-app'): Manifest
    {
        $manifest = $this->createMock(Manifest::class);
        $metadata = Metadata::fromArray([
            'name' => $appName,
            'label' => [],
            'author' => 'myApp',
            'copyright' => 'none',
            'license' => 'none',
            'version' => '99',
        ]);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
