<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton\Response;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ActionButtonResponseFactory::class)]
class ActionButtonResponseFactoryTest extends TestCase
{
    private ActionButtonResponseFactory $actionButtonResponseFactory;

    private AppAction $action;

    protected function setUp(): void
    {
        $signer = $this->createMock(QuerySigner::class);
        $signer->method('signUri')->willReturn(new Uri('http://signed.url'));

        $this->actionButtonResponseFactory = new ActionButtonResponseFactory([
            new NotificationResponseFactory(),
            new ReloadDataResponseFactory(),
            new OpenNewTabResponseFactory($signer),
            new OpenModalResponseFactory($signer),
        ]);

        $app = new AppEntity();
        $app->setName('TestApp');
        $app->setId(Uuid::randomHex());
        $app->setAppSecret('app-secret');
        $app->setVersion('1.0.0');

        $this->action = new AppAction(
            $app,
            new Source('http://shop.url', 'shop-id', '1.0.0'),
            'http://target.url',
            'customer',
            'action-name',
            [Uuid::randomHex(), Uuid::randomHex()],
            'action-it'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param class-string $response
     */
    #[DataProvider('provideActionTypes')]
    public function testFactoryCreatesCorrespondingResponse(string $actionType, array $payload, string $response): void
    {
        $notificationResponse = $this->actionButtonResponseFactory->createFromResponse(
            $this->action,
            $actionType,
            $payload,
            Context::createDefaultContext()
        );
        static::assertInstanceOf($response, $notificationResponse);
    }

    public function testFactoryThrowException(): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException(
            $this->action->getActionId(),
            \sprintf('No factory found for action type "%s"', 'test')
        ));

        $this->actionButtonResponseFactory->createFromResponse(
            $this->action,
            'test',
            [],
            Context::createDefaultContext()
        );
    }

    /**
     * @return array<int, array<int, array<string, bool|string>|string>>
     */
    public static function provideActionTypes(): array
    {
        return [
            [
                NotificationResponse::ACTION_TYPE,
                ['status' => 'success', 'message' => 'This is success'],
                NotificationResponse::class,
            ],
            [
                ReloadDataResponse::ACTION_TYPE,
                [],
                ReloadDataResponse::class,
            ],
            [
                OpenNewTabResponse::ACTION_TYPE,
                ['redirectUrl' => 'https://www.google.com/'],
                OpenNewTabResponse::class,
            ],
            [
                OpenModalResponse::ACTION_TYPE,
                ['iframeUrl' => 'http://iframe.url', 'size' => 'medium', 'expand' => false],
                OpenModalResponse::class,
            ],
        ];
    }
}
