<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ReloadDataResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private ReloadDataResponseFactory $factory;

    private AppAction $action;

    public function setUp(): void
    {
        $this->factory = $this->getContainer()->get(ReloadDataResponseFactory::class);
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
    public function testSupportsOnlyOpenNewTabActionType(string $actionType, bool $isSupported): void
    {
        static::assertEquals($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesReloadDataResponse(): void
    {
        $response = $this->factory->create($this->action, [], Context::createDefaultContext());

        static::assertInstanceOf(ReloadDataResponse::class, $response);
    }

    public function provideActionTypes(): array
    {
        return [
            [OpenModalResponseFactory::ACTION_TYPE, false],
            [OpenNewTabResponseFactory::ACTION_TYPE, false],
            [ReloadDataResponseFactory::ACTION_TYPE, true],
            [NotificationResponseFactory::ACTION_TYPE, false],
        ];
    }
}
