<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ActionButtonResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private ActionButtonResponseFactory $actionButtonResponseFactory;

    private AppAction $action;

    public function setUp(): void
    {
        $this->actionButtonResponseFactory = $this->getContainer()->get(ActionButtonResponseFactory::class);
        $this->action = new AppAction(
            'http://target.url',
            'http://shop.url',
            '1.0.0',
            'customer',
            'action-name',
            [Uuid::randomHex(), Uuid::randomHex()],
            'app-secret',
            'shop-id',
            'action-it'
        );
    }

    /**
     * @dataProvider provideActionTypes
     */
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
        static::expectException(ActionProcessException::class);
        static::expectExceptionMessage('No factory found for action type "test"');

        $this->actionButtonResponseFactory->createFromResponse(
            $this->action,
            'test',
            [],
            Context::createDefaultContext()
        );
    }

    public function provideActionTypes(): array
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
