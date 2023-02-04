<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton\Response;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class OpenModalResponseFactoryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private OpenModalResponseFactory $factory;

    private AppAction $action;

    public function setUp(): void
    {
        $this->factory = $this->getContainer()->get(OpenModalResponseFactory::class);
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
    public function testSupportsOnlyOpenModalActionType(string $actionType, bool $isSupported): void
    {
        static::assertEquals($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesOpenModalResponse(): void
    {
        $response = $this->factory->create($this->action, [
            'iframeUrl' => 'http://iframe.url',
            'size' => 'medium',
            'expand' => false,
        ], Context::createDefaultContext());

        static::assertInstanceOf(OpenModalResponse::class, $response);
    }

    /**
     * @param array<bool|string> $payload
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
            [OpenModalResponse::ACTION_TYPE, true],
            [OpenNewTabResponse::ACTION_TYPE, false],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }

    /**
     * @return array<array<array<bool|string>|string>>
     */
    public function provideInvalidPayloads(): array
    {
        return [
            [
                ['size' => 'medium', 'expand' => false],
                'The app provided an invalid iframeUrl',
            ],
            [
                ['iframeUrl' => '', 'size' => 'medium', 'expand' => false],
                'The app provided an invalid iframeUrl',
            ],
            [
                ['iframeUrl' => 'http://iframe.url', 'expand' => false],
                'The app provided an invalid size',
            ],
            [
                ['iframeUrl' => 'http://iframe.url', 'size' => '', 'expand' => false],
                'The app provided an invalid size',
            ],
            [
                ['iframeUrl' => 'http://iframe.url', 'size' => 'xl', 'expand' => false],
                'The app provided an invalid size',
            ],
        ];
    }
}
