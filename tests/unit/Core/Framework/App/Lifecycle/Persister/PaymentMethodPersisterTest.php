<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\PaymentMethodPersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(PaymentMethodPersister::class)]
class PaymentMethodPersisterTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentMethodCollection>
     */
    private StaticEntityRepository $paymentMethodRepository;

    private PaymentMethodPersister $persister;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = new StaticEntityRepository([]);
        $this->persister = new PaymentMethodPersister(
            $this->paymentMethodRepository,
            $this->createMock(MediaService::class),
        );
    }

    public function testPersistUpsertsConfiguredPaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifest_payment_methods.xml');

        $this->paymentMethodRepository->addSearch(new PaymentMethodCollection());

        $this->persister->persist($this->buildContext($manifest, $appId));

        $paymentMethods = $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(2, $paymentMethods);
        $this->assertPaymentMethodPayload($paymentMethods[0], $appId, 'paymentMethodOne');
        $this->assertPaymentMethodPayload($paymentMethods[1], $appId, 'paymentMethodTwo');
        static::assertSame('https://payment.example.com/pay', $paymentMethods[0]['appPaymentMethod']['payUrl']);
        static::assertSame('https://payment.example.com/finalize', $paymentMethods[0]['appPaymentMethod']['finalizeUrl']);
    }

    public function testActivateUpdatesInactivePaymentMethods(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $paymentMethodIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->paymentMethodRepository->addSearch($paymentMethodIds);

        $this->persister->activate($this->buildApp($appId), $context);

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

        $this->persister->deactivate($this->buildApp($appId), $context);

        static::assertSame([
            ['id' => $paymentMethodIds[0], 'active' => false],
            ['id' => $paymentMethodIds[1], 'active' => false],
        ], $this->paymentMethodRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    private function buildContext(Manifest $manifest, string $appId): AppLifecycleContext
    {
        $app = $this->buildApp($appId);
        $app->setActive(true);
        $app->setAppSecret('test-secret');

        return new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
            isInstall: true,
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
