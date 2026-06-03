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
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
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
#[CoversClass(OpenNewTabResponseFactory::class)]
class OpenNewTabResponseFactoryTest extends TestCase
{
    private OpenNewTabResponseFactory $factory;

    private AppAction $action;

    private QuerySigner&MockObject $signer;

    protected function setUp(): void
    {
        $this->signer = $this->createMock(QuerySigner::class);
        $this->factory = new OpenNewTabResponseFactory($this->signer);

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
    public function testSupportsOnlyOpenNewTabActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    public function testCreatesOpenNewTabResponse(): void
    {
        $context = Context::createDefaultContext();
        $this->signer->expects($this->once())
            ->method('signUri')
            ->with('http://redirect.url', $this->action->getApp(), $context)
            ->willReturn(new Uri('http://redirect.url?shopware-shop-signature=signature'));

        $response = $this->factory->create($this->action, [
            'redirectUrl' => 'http://redirect.url',
        ], $context);

        static::assertInstanceOf(OpenNewTabResponse::class, $response);
    }

    /**
     * @param array<string, mixed> $payload
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
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, true],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }

    /**
     * @return array<array<string|array<string, mixed>>>
     */
    public static function provideInvalidPayloads(): array
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
