<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Test\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\HttpFoundation\Request;

class AppUrlVerifierTest extends TestCase
{
    /**
     * @var Client|MockObject
     */
    private $guzzleMock;

    public function setUp(): void
    {
        $this->guzzleMock = $this->createMock(Client::class);
    }

    public function testReturnsTrueIfAppEnvIsNotProd(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, 'dev');

        static::assertTrue($verifier->isAppUrlReachable(new Request()));
    }

    public function testReturnsTrueIfRequestIsMadeToSameDomain(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, 'prod');

        $request = Request::create(EnvironmentHelper::getVariable('APP_URL') . '/api/_info/config');

        static::assertTrue($verifier->isAppUrlReachable($request));
    }

    public function testReturnsTrueIfAppUrlIsReachable(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                ]
            )->willReturn(new Response());

        $verifier = new AppUrlVerifier($this->guzzleMock, 'prod');

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertTrue($verifier->isAppUrlReachable($request));
    }

    public function testReturnsFalseOnNot200Status(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                ]
            )->willReturn(new Response(404));

        $verifier = new AppUrlVerifier($this->guzzleMock, 'prod');

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }

    public function testReturnsFalseOnException(): void
    {
        $this->guzzleMock->expects(static::once())
            ->method('get')
            ->with(
                EnvironmentHelper::getVariable('APP_URL') . '/api/_info/version',
                [
                    'headers' => [
                        'Authorization' => 'Bearer Token',
                    ],
                ]
            )->willThrowException(new TransferException());

        $verifier = new AppUrlVerifier($this->guzzleMock, 'prod');

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }
}
