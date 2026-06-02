<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton\Response;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(OpenModalResponseFactory::class)]
class OpenModalResponseFactoryTest extends TestCase
{
    private OpenModalResponseFactory $factory;

    private AppAction $action;

    private QuerySigner&MockObject $signer;

    protected function setUp(): void
    {
        $this->signer = $this->createMock(QuerySigner::class);
        $this->factory = new OpenModalResponseFactory($this->signer);

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

    #[DataProvider('provideActionTypes')]
    public function testSupportsOnlyOpenModalActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesOpenModalResponse(): void
    {
        $context = Context::createDefaultContext();
        $this->signer->expects($this->once())
            ->method('signUri')
            ->with('http://iframe.url', $this->action->getApp(), $context)
            ->willReturn(new Uri('http://iframe.url?shopware-shop-signature=signature'));

        $response = $this->factory->create($this->action, [
            'iframeUrl' => 'http://iframe.url',
            'size' => 'medium',
            'expand' => false,
        ], $context);

        static::assertInstanceOf(OpenModalResponse::class, $response);
    }

    /**
     * @param array<bool|string> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testThrowsExceptionWhenValidationFails(array $payload, string $message): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), $message));

        $this->factory->create(
            $this->action,
            $payload,
            Context::createDefaultContext()
        );
    }

    /**
     * @return array<array<string|bool>>
     */
    public static function provideActionTypes(): array
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
    public static function provideInvalidPayloads(): array
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
