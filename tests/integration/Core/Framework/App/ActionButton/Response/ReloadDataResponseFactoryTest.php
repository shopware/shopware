<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class ReloadDataResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private ReloadDataResponseFactory $factory;

    private AppAction $action;

    protected function setUp(): void
    {
        $this->factory = $this->getContainer()->get(ReloadDataResponseFactory::class);
        $this->action = new AppAction(
            'http://target.url',
            new Source('http://shop.url', 'shop-id', '1.0.0'),
            'customer',
            'action-name',
            [Uuid::randomHex(), Uuid::randomHex()],
            'app-secret',
            'action-it'
        );
    }

    #[DataProvider('provideActionTypes')]
    public function testSupportsOnlyReloadDataActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesReloadDataResponse(): void
    {
        $response = $this->factory->create($this->action, [], Context::createDefaultContext());

        static::assertInstanceOf(ReloadDataResponse::class, $response);
    }

    /**
     * @return array<int, array<string|bool>>
     */
    public static function provideActionTypes(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, false],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, false],
            [ReloadDataResponse::ACTION_TYPE, true],
        ];
    }
}
