<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class NotificationResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private NotificationResponseFactory $factory;

    private AppAction $action;

    public function setUp(): void
    {
        $this->factory = $this->getContainer()->get(NotificationResponseFactory::class);
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
    public function testSupportsOnlyNotificationActionType(string $actionType, bool $isSupported): void
    {
        static::assertEquals($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesNotificationResponse(): void
    {
        $response = $this->factory->create($this->action, [], Context::createDefaultContext());

        static::assertInstanceOf(NotificationResponse::class, $response);
    }

    public function provideActionTypes(): array
    {
        return [
            [OpenModalResponseFactory::ACTION_TYPE, false],
            [OpenNewTabResponseFactory::ACTION_TYPE, false],
            [ReloadDataResponseFactory::ACTION_TYPE, false],
            [NotificationResponseFactory::ACTION_TYPE, true],
        ];
    }
}
