<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(Executor::class)]
#[Package('core')]
class ExecutorTest extends TestCase
{
    public function testConnectionProblemsGotConverted(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack
            ->method('getCurrentRequest')
            ->willReturn(new SfRequest());

        $guzzleClient = new Client([
            'handler' => function (): void {
                throw new ConnectException('Connection problems', new Request('POST', 'https://example.com'));
            },
        ]);

        $executor = new Executor(
            $guzzleClient,
            $this->createMock(LoggerInterface::class),
            $this->createMock(ActionButtonResponseFactory::class),
            $this->createMock(ShopIdProvider::class),
            $this->createMock(RouterInterface::class),
            $requestStack,
            $this->createMock(KernelInterface::class)
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('connection problems');

        $app = new AppEntity();
        $app->setAppSecret('devSecret');

        $appAction = new AppAction($app, new Source('https://localhost', 'asd', '1.0.0'), 'https://example.com', 'GET', 'action-id', [Uuid::randomHex()], '123123123');

        $executor->execute(
            $appAction,
            Context::createDefaultContext()
        );
    }
}
