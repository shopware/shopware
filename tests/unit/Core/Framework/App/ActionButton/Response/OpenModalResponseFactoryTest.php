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
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse;
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
#[CoversClass(OpenModalResponseFactory::class)]
class OpenModalResponseFactoryTest extends TestCase
{
    private OpenModalResponseFactory $factory;

    private AppAction $action;

    private QuerySigner&Stub $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = static::createStub(QuerySigner::class);
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
        $signer = static::createMock(QuerySigner::class);
        $signer->expects($this->once())
            ->method('signUri')
            ->with('http://iframe.url', $this->action->getApp(), $context)
            ->willReturn(new Uri('http://iframe.url?shopware-shop-signature=signature'));
        $factory = new OpenModalResponseFactory($signer);

        $response = $factory->create($this->action, [
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

        $this->createWithErrorsAsExceptions($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideRejectedIframeUrlPayloads')]
    public function testRejectsIframeUrlThatIsNotANonEmptyString(array $payload): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), 'The app provided an invalid iframeUrl'));

        $this->createWithErrorsAsExceptions($payload);
    }

    public function testAcceptsZeroStringIframeUrl(): void
    {
        $this->signer->method('signUri')->willReturn(new Uri('0?signed=1'));

        $response = $this->factory->create(
            $this->action,
            ['iframeUrl' => '0', 'size' => 'medium', 'expand' => false],
            Context::createDefaultContext()
        );

        static::assertSame('0?signed=1', $response->jsonSerialize()['iframeUrl']);
    }

    #[DataProvider('provideNonStringTruthyValues')]
    public function testRejectsNonStringTruthyIframeUrlWithSecret(mixed $value): void
    {
        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), 'The app provided an invalid iframeUrl'));

        $this->factory->create(
            $this->action,
            ['iframeUrl' => $value, 'size' => 'medium', 'expand' => false],
            Context::createDefaultContext()
        );
    }

    #[DataProvider('provideNonStringTruthyValues')]
    public function testRejectsNonStringTruthyIframeUrlWithoutSecret(mixed $value): void
    {
        $this->action->getApp()->setAppSecret(null);

        $this->expectExceptionObject(AppException::actionButtonProcessException($this->action->getActionId(), 'The app provided an invalid iframeUrl'));

        $this->factory->create(
            $this->action,
            ['iframeUrl' => $value, 'size' => 'medium', 'expand' => false],
            Context::createDefaultContext()
        );
    }

    public function testKeepsIframeUrlUnsignedWithoutSecret(): void
    {
        $this->action->getApp()->setAppSecret(null);

        $response = $this->factory->create(
            $this->action,
            ['iframeUrl' => 'http://iframe.url', 'size' => 'medium', 'expand' => false],
            Context::createDefaultContext()
        );

        static::assertSame('http://iframe.url', $response->jsonSerialize()['iframeUrl']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideRejectedIframeUrlPayloads(): iterable
    {
        $base = ['size' => 'medium', 'expand' => false];

        yield 'missing' => [$base];
        yield 'null' => [$base + ['iframeUrl' => null]];
        yield 'empty string' => [$base + ['iframeUrl' => '']];
        yield 'int zero' => [$base + ['iframeUrl' => 0]];
        yield 'float zero' => [$base + ['iframeUrl' => 0.0]];
        yield 'false' => [$base + ['iframeUrl' => false]];
        yield 'empty array' => [$base + ['iframeUrl' => []]];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideNonStringTruthyValues(): iterable
    {
        yield 'int' => [1];
        yield 'float' => [1.5];
        yield 'true' => [true];
        yield 'non-empty array' => [['http://iframe.url']];
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

    /**
     * @param array<string, mixed> $payload
     */
    private function createWithErrorsAsExceptions(array $payload): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & ~(\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR)) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $this->factory->create($this->action, $payload, Context::createDefaultContext());
        } finally {
            restore_error_handler();
        }
    }
}
