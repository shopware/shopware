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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\Requirements\RequiresPublicAccess;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * @internal
 */
#[CoversClass(RequiresPublicAccess::class)]
class RequiresPublicAccessTest extends TestCase
{
    use EnvTestBehaviour;

    private RequiresPublicAccess $requirement;

    private MockHandler $mockHandler;

    private Client $guzzle;

    private SecureUrlValidator $secureUrlValidator;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->guzzle = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->secureUrlValidator = new SecureUrlValidator();
        $this->requirement = new RequiresPublicAccess($this->secureUrlValidator, $this->guzzle);
    }

    public function testSatisfiedReturnsFalseWhenAppUrlNotSet(): void
    {
        $this->setEnvVars(['APP_URL' => null]);
        $manifest = $this->createMock(Manifest::class);

        $result = $this->requirement->satisfied($manifest);

        static::assertFalse($result);
    }

    public function testSatisfiedReturnsFalseWhenUrlNotValid(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://localhost']);  // localhost is invalid
        $manifest = $this->createMock(Manifest::class);

        $result = $this->requirement->satisfied($manifest);

        static::assertFalse($result);
    }

    public function testSatisfiedReturnsTrueWhenHealthCheckReturns200(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        $manifest = $this->createMock(Manifest::class);

        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));

        $result = $this->requirement->satisfied($manifest);

        static::assertTrue($result);
    }

    public function testSatisfiedReturnsFalseWhenHealthCheckReturnsNon200(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        $manifest = $this->createMock(Manifest::class);

        $this->mockHandler->append(new Response(HttpResponse::HTTP_INTERNAL_SERVER_ERROR));

        $result = $this->requirement->satisfied($manifest);

        static::assertFalse($result);
    }

    #[DataProvider('guzzleExceptionProvider')]
    public function testSatisfiedReturnsFalseWhenGuzzleThrowsException(\Throwable $exception): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        $manifest = $this->createMock(Manifest::class);

        $this->mockHandler->append($exception);

        $result = $this->requirement->satisfied($manifest);

        static::assertFalse($result);
    }

    public static function guzzleExceptionProvider(): \Generator
    {
        yield 'ConnectException' => [new ConnectException('Connection failed', new Request('GET', 'test'))];
        yield 'RequestException' => [new RequestException('Request failed', new Request('GET', 'test'))];
    }

    public function testSatisfiedStripsTrailingSlashFromAppUrl(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com/']);
        $manifest = $this->createMock(Manifest::class);

        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));

        $result = $this->requirement->satisfied($manifest);

        static::assertTrue($result);
    }

    public function testResultIsCached(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        $manifest = $this->createMock(Manifest::class);

        // Only one response should be consumed due to caching
        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));

        // Call twice to verify caching - second call should use cached result
        $result1 = $this->requirement->satisfied($manifest);
        $result2 = $this->requirement->satisfied($manifest);

        static::assertTrue($result1);
        static::assertTrue($result2);

        // Verify only one HTTP request was made (due to caching)
        static::assertCount(0, $this->mockHandler);
    }

    public function testResetClearsCachedResult(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://example.com']);
        $manifest = $this->createMock(Manifest::class);

        // First response: success
        $this->mockHandler->append(new Response(HttpResponse::HTTP_OK));
        // Second response: failure (to prove cache was cleared)
        $this->mockHandler->append(new Response(HttpResponse::HTTP_INTERNAL_SERVER_ERROR));

        // First call - should return true and cache the result
        $result1 = $this->requirement->satisfied($manifest);
        static::assertTrue($result1);

        // Reset cache
        $this->requirement->reset();

        // Second call - should make new HTTP request and return false
        $result2 = $this->requirement->satisfied($manifest);
        static::assertFalse($result2);

        // Verify both HTTP requests were made
        static::assertCount(0, $this->mockHandler);
    }
}
