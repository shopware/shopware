<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 * @covers \Shopware\Core\Maintenance\System\Service\AppUrlVerifier
 */
class AppUrlVerifierTest extends TestCase
{
    /**
     * @var Client&MockObject
     */
    private $guzzleMock;

    /**
     * @var EntityRepository&MockObject
     */
    private $appRepository;

    public function setUp(): void
    {
        $this->guzzleMock = $this->createMock(Client::class);
        $this->appRepository = $this->createMock(EntityRepository::class);
    }

    public function testAppUrlReachableReturnsTrueIfAppEnvIsNotProd(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'dev', false);

        static::assertTrue($verifier->isAppUrlReachable(new Request()));
    }

    public function testAppUrlReachableReturnsTrueIfAppUrlCheckIsDisabled(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', true);

        static::assertTrue($verifier->isAppUrlReachable(new Request()));
    }

    public function testAppUrlReachableReturnsTrueIfRequestIsMadeToSameDomain(): void
    {
        $this->guzzleMock->expects(static::never())
            ->method('get');

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

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

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

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

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

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

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

        $request = Request::create('http://some.host/api/_info/config');
        $request->headers->set('Authorization', 'Bearer Token');

        static::assertFalse($verifier->isAppUrlReachable($request));
    }

    public function testAppsThatNeedAppUrlReturnFalseWithoutAppsThatRequireRegistration(): void
    {
        $this->appRepository->expects(static::once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()));

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

        static::assertFalse($verifier->hasAppsThatNeedAppUrl(Context::createDefaultContext()));
    }

    public function testAppsThatNeedAppUrlReturnTrueWithAppsThatRequireRegistration(): void
    {
        $this->appRepository->expects(static::once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(1, [['primaryKey' => Uuid::randomHex(), 'data' => []]], new Criteria(), Context::createDefaultContext()));

        $verifier = new AppUrlVerifier($this->guzzleMock, $this->appRepository, 'prod', false);

        static::assertTrue($verifier->hasAppsThatNeedAppUrl(Context::createDefaultContext()));
    }
}
