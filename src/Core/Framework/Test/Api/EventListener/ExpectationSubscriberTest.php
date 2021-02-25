<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\EventListener;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\EventListener\ExpectationSubscriber;
use Shopware\Core\Framework\Api\Exception\ExceptionFailedException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExpectationSubscriberTest extends TestCase
{
    private ExpectationSubscriber $expectationSubscriber;

    public function setUp(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', []);
        InstalledVersions::reload([
            'versions' => [
                'shopware/core' => [
                    'version' => '6.3.0.0',
                ],
            ],
        ]);
    }

    public function testExpectFailsOutdatedShopwareVersion(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'shopware/core:~6.4');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [$this, 'setUp'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        static::expectException(ExceptionFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExpectMatchesShopwareVersion(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'shopware/core:~6.3.0.0');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [$this, 'setUp'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectMatchesShopwareVersionButNotPlugin(): void
    {
        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'shopware/core:~6.3.0.0,swag/paypal:*');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [$this, 'setUp'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        static::expectException(ExceptionFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExpectMatchesShopwareVersionAndPlugin(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', [['composerName' => 'swag/paypal', 'active' => true, 'version' => '1.0.0']]);

        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'shopware/core:~6.3.0.0,swag/paypal:*');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [$this, 'setUp'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->expectationSubscriber->checkExpectations($event);
    }

    public function testExpectMatchesShopwareVersionAndPluginIsNotActive(): void
    {
        $this->expectationSubscriber = new ExpectationSubscriber('6.3.0.0', [['composerName' => 'swag/paypal', 'active' => false, 'version' => '1.0.0']]);

        $request = $this->makeRequest();
        $request->headers->set(PlatformRequest::HEADER_EXPECT_PACKAGES, 'shopware/core:~6.3.0.0,swag/paypal:*');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [$this, 'setUp'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        static::expectException(ExceptionFailedException::class);

        $this->expectationSubscriber->checkExpectations($event);
    }

    private function makeRequest(): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, new RouteScope(['scopes' => [ApiRouteScope::ID]]));

        return $request;
    }
}
