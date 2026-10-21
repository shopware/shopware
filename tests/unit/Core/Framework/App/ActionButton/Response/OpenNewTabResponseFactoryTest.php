<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ActionButton\Response;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OpenNewTabResponseFactory::class)]
class OpenNewTabResponseFactoryTest extends TestCase
{
    private OpenNewTabResponseFactory $factory;

    private AppAction $action;

    private QuerySigner&Stub $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = static::createStub(QuerySigner::class);
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

    #[DataProvider('actionTypesProvider')]
    public function testSupportsOnlyOpenNewTabActionType(string $actionType, bool $isSupported): void
    {
        static::assertSame($isSupported, $this->factory->supports($actionType));
    }

    /**
     * @return array<array<string|bool>>
     */
    public static function actionTypesProvider(): array
    {
        return [
            [NotificationResponse::ACTION_TYPE, false],
            [OpenModalResponse::ACTION_TYPE, false],
            [OpenNewTabResponse::ACTION_TYPE, true],
            [ReloadDataResponse::ACTION_TYPE, false],
        ];
    }

    public function testCreatesOpenNewTabResponse(): void
    {
        $context = Context::createDefaultContext();
        $signer = static::createMock(QuerySigner::class);
        $signer->expects($this->once())
            ->method('signUri')
            ->with('http://redirect.url', $this->action->getApp(), $context)
            ->willReturn(new Uri('http://redirect.url?shopware-shop-signature=signature'));
        $factory = new OpenNewTabResponseFactory($signer);

        $response = $factory->create($this->action, [
            'redirectUrl' => 'http://redirect.url',
        ], $context);

        static::assertInstanceOf(OpenNewTabResponse::class, $response);
    }

    #[DataProvider('falsyAppSecretProvider')]
    public function testKeepsRedirectUrlUnsignedWithoutSecret(?string $appSecret): void
    {
        $this->action->getApp()->setAppSecret($appSecret);

        $response = $this->factory->create(
            $this->action,
            ['redirectUrl' => 'http://redirect.url'],
            Context::createDefaultContext()
        );

        static::assertSame('http://redirect.url', $response->jsonSerialize()['redirectUrl']);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function falsyAppSecretProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'zero string' => ['0'];
    }

    public function testAcceptsZeroStringRedirectUrl(): void
    {
        $this->signer->method('signUri')->willReturn(new Uri('0?signed=1'));

        $response = $this->factory->create(
            $this->action,
            ['redirectUrl' => '0'],
            Context::createDefaultContext()
        );

        static::assertSame('0?signed=1', $response->jsonSerialize()['redirectUrl']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('rejectedRedirectUrlPayloadsProvider')]
    public function testRejectsRedirectUrlThatIsNotANonEmptyString(array $payload): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), 'The app provided an invalid redirectUrl'));

        $this->createWithErrorsAsExceptions($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rejectedRedirectUrlPayloadsProvider(): iterable
    {
        $base = [];

        yield 'missing' => [$base];
        yield 'null' => [$base + ['redirectUrl' => null]];
        yield 'empty string' => [$base + ['redirectUrl' => '']];
        yield 'int zero' => [$base + ['redirectUrl' => 0]];
        yield 'float zero' => [$base + ['redirectUrl' => 0.0]];
        yield 'false' => [$base + ['redirectUrl' => false]];
        yield 'empty array' => [$base + ['redirectUrl' => []]];
    }

    #[DataProvider('nonStringTruthyValuesProvider')]
    public function testRejectsNonStringTruthyRedirectUrl(mixed $value): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), 'The app provided an invalid redirectUrl'));

        $this->factory->create(
            $this->action,
            ['redirectUrl' => $value],
            Context::createDefaultContext()
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringTruthyValuesProvider(): iterable
    {
        yield 'int' => [1];
        yield 'float' => [1.5];
        yield 'true' => [true];
        yield 'non-empty array' => [['http://redirect.url']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createWithErrorsAsExceptions(array $payload): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $this->factory->create($this->action, $payload, Context::createDefaultContext());
        } finally {
            restore_error_handler();
        }
    }
}
