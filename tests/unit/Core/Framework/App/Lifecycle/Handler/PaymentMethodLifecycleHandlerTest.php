<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(PaymentMethodLifecycleHandler::class)]
class PaymentMethodLifecycleHandlerTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentMethodCollection>
     */
    private StaticEntityRepository $paymentMethodRepository;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private PaymentMethodLifecycleHandler $persister;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new StaticEntityRepository([]);
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->persister = new PaymentMethodLifecycleHandler(
            $this->paymentMethodRepository,
            $this->mediaRepository,
            $this->createMock(MediaService::class),
        );
    }

    public function testPersistUpsertsConfiguredPaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection());

        $this->persister->install($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(2, $paymentMethods);
        $this->assertPaymentMethodPayload($paymentMethods[0], $appId, 'paymentMethodOne');
        $this->assertPaymentMethodPayload($paymentMethods[1], $appId, 'paymentMethodTwo');
        static::assertSame('https://payment.example.com/pay', $paymentMethods[0]['appPaymentMethod']['payUrl']);
        static::assertSame('https://payment.example.com/finalize', $paymentMethods[0]['appPaymentMethod']['finalizeUrl']);
    }

    public function testPersistReusesExistingMediaByFileNameWhenOriginalMediaLinkIsMissing(): void
    {
        $appId = Uuid::randomHex();
        $existingMediaId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_method_with_icon.xml');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        static::assertIsString($png);

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection());
        $this->mediaRepository->addSearch([$existingMediaId]);

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('saveFile')
            ->with(
                static::anything(),
                'png',
                static::anything(),
                'payment_app_paymentPersister_paymentWithIcon',
                static::anything(),
                PaymentMethodDefinition::ENTITY_NAME,
                $existingMediaId,
                false
            )
            ->willReturn($existingMediaId);

        $persister = new PaymentMethodLifecycleHandler($this->paymentMethodRepository, $this->mediaRepository, $mediaService);

        $persister->install($this->buildContext($manifest, $appId, new StaticFilesystem(['icon.png' => $png])));

        $payloads = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(1, $payloads);
        static::assertIsArray($payloads[0]['appPaymentMethod']);
        static::assertSame($existingMediaId, $payloads[0]['appPaymentMethod']['originalMediaId']);
    }

    public function testActivateUpdatesInactivePaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $paymentMethodIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->paymentMethodRepository->addSearch($paymentMethodIds);

        $this->persister->activate(new AppActivationContext($this->buildApp($appId), $context));

        static::assertSame([
            ['id' => $paymentMethodIds[0], 'active' => true],
            ['id' => $paymentMethodIds[1], 'active' => true],
        ], $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActivePaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $paymentMethodIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->paymentMethodRepository->addSearch($paymentMethodIds);

        $this->persister->deactivate(new AppActivationContext($this->buildApp($appId), $context));

        static::assertSame([
            ['id' => $paymentMethodIds[0], 'active' => false],
            ['id' => $paymentMethodIds[1], 'active' => false],
        ], $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    private function buildContext(Manifest $manifest, string $appId, ?Filesystem $appFilesystem = null): AppPersistContext
    {
        $app = $this->buildApp($appId);
        $app->setActive(true);
        $app->setAppSecret('test-secret');

        return new AppPersistContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: $appFilesystem ?? new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    private function buildApp(string $appId): AppEntity
    {
        $app = new AppEntity();
        $app->setId($appId);

        return $app;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertPaymentMethodPayload(array $payload, string $appId, string $identifier): void
    {
        static::assertSame(\sprintf('app\\paymentPersister_%s', $identifier), $payload['handlerIdentifier']);
        static::assertSame(\sprintf('payment_paymentPersister_%s', $identifier), $payload['technicalName']);
        static::assertTrue($payload['afterOrderEnabled']);
        static::assertIsArray($payload['appPaymentMethod']);
        static::assertSame($identifier, $payload['appPaymentMethod']['identifier']);
        static::assertSame($appId, $payload['appPaymentMethod']['appId']);
        static::assertSame('paymentPersister', $payload['appPaymentMethod']['appName']);
    }
}
