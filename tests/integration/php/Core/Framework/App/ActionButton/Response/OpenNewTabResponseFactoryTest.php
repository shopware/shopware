<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class OpenNewTabResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private OpenNewTabResponseFactory $factory;

    private AppAction $action;

    public function setUp(): void
    {
        $this->factory = $this->getContainer()->get(OpenNewTabResponseFactory::class);
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

    public function testCreatesOpenNewTabResponse(): void
    {
        $response = $this->factory->create($this->action, [
            'redirectUrl' => 'http://redirect.url',
        ], Context::createDefaultContext());

        static::assertInstanceOf(OpenNewTabResponse::class, $response);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @dataProvider provideInvalidPayloads
     */
    public function testThrowsExceptionWhenValidationFails(array $payload, string $message): void
    {
        static::expectException(ActionProcessException::class);
        static::expectExceptionMessage($message);

        $this->factory->create(
            $this->action,
            $payload,
            Context::createDefaultContext()
        );
    }

    /**
     * @return array<array<string|bool>>
     */
    public function provideActionTypes(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, false],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, true],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }

    /**
     * @return array<array<string|array<string, mixed>>>
     */
    public function provideInvalidPayloads(): array
    {
        return [
            [
                [],
                'The app provided an invalid redirectUrl',
            ],
            [
                ['redirectUrl' => ''],
                'The app provided an invalid redirectUrl',
            ],
        ];
    }
}
