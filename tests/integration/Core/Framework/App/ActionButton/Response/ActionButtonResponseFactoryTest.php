<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class ActionButtonResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private ActionButtonResponseFactory $actionButtonResponseFactory;

    private AppAction $action;

    protected function setUp(): void
    {
        $this->actionButtonResponseFactory = $this->getContainer()->get(ActionButtonResponseFactory::class);
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setAppSecret('app-secret');
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
        static::expectException(AppException::class);
        static::expectExceptionMessage('No factory found for action type "test"');

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
