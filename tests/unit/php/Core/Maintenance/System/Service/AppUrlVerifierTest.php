<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Maintenance\System\Service\AppUrlVerifier
 */
class AppUrlVerifierTest extends TestCase
{
    private Client&MockObject $guzzleMock;

    private Connection&MockObject $connection;

    public function setUp(): void
    {
        $this->guzzleMock = $this->createMock(Client::class);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testAppUrlReachableReturnsTrueIfAppEnvIsNotProd(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'dev', false);

        static::assertTrue($verifier->isAppUrlReachable(new Request()));
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlCheckIsDisabled(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', true);

        static::assertTrue($verifier->isAppUrlReachable(new Request()));
    }

    public function testAppUrlReachableReturnsTrueIfRequestIsMadeToSameDomain(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        $request = Request::create(EnvironmentHelper::getVariable('APP_URL') . '/api/_info/config');

        static::assertTrue($verifier->isAppUrlReachable($request));
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlIsReachable(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                    RequestOptions::TIMEOUT => 1,
                    RequestOptions::CONNECT_TIMEOUT => 1,
                ]
            )->willReturn(new Response());

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertTrue($verifier->isAppUrlReachable($request));
    }

    public function testAppUrlReachableReturnsFalseOnNot200Status(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                    RequestOptions::TIMEOUT => 1,
                    RequestOptions::CONNECT_TIMEOUT => 1,
                ]
            )->willReturn(new Response(404));

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }

    public function testAppUrlReachableReturnsFalseOnException(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                    RequestOptions::TIMEOUT => 1,
                    RequestOptions::CONNECT_TIMEOUT => 1,
                ]
            )->willThrowException(new TransferException());

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }

    public function testAppsThatNeedAppUrlReturnFalseWithoutAppsThatRequireRegistration(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('0');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        static::assertFalse($verifier->hasAppsThatNeedAppUrl());
    }

    public function testAppsThatNeedAppUrlReturnTrueWithAppsThatRequireRegistration(): void
    {
        $this->connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn('1');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->connection, 'prod', false);

        static::assertTrue($verifier->hasAppsThatNeedAppUrl());
    }
}
