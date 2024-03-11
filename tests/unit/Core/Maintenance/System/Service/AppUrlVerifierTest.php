<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * @internal
 */
#[CoversClass(AppUrlVerifier::class)]
class AppUrlVerifierTest extends TestCase
{
    private MockHandler $mockHandler;

    private Client $client;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->client = new Client(['handler' => $this->mockHandler]);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testAppUrlReachableReturnsTrueIfAppEnvIsNotProd(): void
    {
        $verifier = new AppUrlVerifier($this->client, $this->connection, 'dev', false);

        static::assertTrue($verifier->isAppUrlReachable(new SymfonyRequest()));

        $request = $this->mockHandler->getLastRequest();
        static::assertNull($request);
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlCheckIsDisabled(): void
    {
        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', true);

        static::assertTrue($verifier->isAppUrlReachable(new SymfonyRequest()));

        $request = $this->mockHandler->getLastRequest();
        static::assertNull($request);
    }

    public function testAppUrlReachableReturnsTrueIfRequestIsMadeToSameDomain(): void
    {
        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', false);

        $request = SymfonyRequest::create(EnvironmentHelper::getVariable('APP_URL') . '/api/_info/config');

        static::assertTrue($verifier->isAppUrlReachable($request));

        $request = $this->mockHandler->getLastRequest();
        static::assertNull($request);
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlIsReachable(): void
    {
        $this->mockHandler->append(new Response());

        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', false);

        $request = SymfonyRequest::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertTrue($verifier->isAppUrlReachable($request));

        $request = $this->mockHandler->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
            (string) $request->getUri()
        );

        $authHeader = $request->getHeader('Authorization');
        static::assertContains('Bearer Token', $authHeader);

        $requestOptions = $this->mockHandler->getLastOptions();
        static::assertSame(1, $requestOptions['timeout']);
        static::assertSame(1, $requestOptions['connect_timeout']);
    }

    public function testAppUrlReachableReturnsFalseOnNot200Status(): void
    {
        $this->mockHandler->append(new Response(404));

        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', false);

        $request = SymfonyRequest::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));

        $request = $this->mockHandler->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
            (string) $request->getUri()
        );

        $authHeader = $request->getHeader('Authorization');
        static::assertContains('Bearer Token', $authHeader);

        $requestOptions = $this->mockHandler->getLastOptions();
        static::assertSame(1, $requestOptions['timeout']);
        static::assertSame(1, $requestOptions['connect_timeout']);
    }

    public function testAppUrlReachableReturnsFalseOnException(): void
    {
        $client = new Client([
            'handler' => MockHandler::createWithMiddleware([new Response(500)]),
        ]);

        $verifier = new AppUrlVerifier($client, $this->connection, 'prod', false);

        $request = SymfonyRequest::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }

    public function testAppsThatNeedAppUrlReturnFalseWithoutAppsThatRequireRegistration(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('0');

        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', false);

        static::assertFalse($verifier->hasAppsThatNeedAppUrl());
    }

    public function testAppsThatNeedAppUrlReturnTrueWithAppsThatRequireRegistration(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('1');

        $verifier = new AppUrlVerifier($this->client, $this->connection, 'prod', false);

        static::assertTrue($verifier->hasAppsThatNeedAppUrl());
    }
}
