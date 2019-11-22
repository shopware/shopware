<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersion;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiVersion\ApiVersionSubscriber;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiVersionSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSubscribedEvents(): void
    {
        static::assertCount(1, ApiVersionSubscriber::getSubscribedEvents());
        static::assertArrayHasKey(KernelEvents::REQUEST, ApiVersionSubscriber::getSubscribedEvents());
    }

    /**
     * @dataProvider checkIfVersionIsSupportedProvider
     */
    public function testCheckIfVersionIsSupported(array $supportedVersions, string $path, bool $shouldThrow): void
    {
        $subscriber = new ApiVersionSubscriber($supportedVersions);

        if ($shouldThrow) {
            $this->expectException(NotFoundHttpException::class);
        }

        $subscriber->checkIfVersionIsSupported(
            new RequestEvent(
                $this->getKernel(),
                new Request([], [], [], [], [], [
                    'REQUEST_URI' => 'http://localhost' . $path,
                ]),
                HttpKernelInterface::MASTER_REQUEST
            )
        );

        static::assertTrue(true, 'No exception thrown and none was expected');
    }

    public function checkIfVersionIsSupportedProvider(): array
    {
        return [
            [[1, 2], '/api/v1/product', false],
            [[1, 2], '/sales-channel-api/v1/product', false],

            [[1, 2], '/api/oauth/token', false],
            [[1, 2], '/admin', false],
            [[1, 2], '/', false],

            [[1, 2], '/api/v0/product', true],
            [[1, 2], '/sales-channel-api/v0/product', true],

            [[1, 2], '/api/v3/product', true],
            [[1, 2], '/sales-channel-api/v3/product', true],
        ];
    }
}
