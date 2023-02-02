<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
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

    /**
     * @return array<int, array<int, string|bool>>
     */
    public function provideActionTypes(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, true],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, false],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }
}
