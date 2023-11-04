<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\MultipleTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\PreparedTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\RefundTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentHandlerRegistryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private PaymentHandlerRegistry $paymentHandlerRegistry;

    private EntityRepository $paymentMethodRepository;

    private EntityRepository $appPaymentMethodRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->appPaymentMethodRepository = $this->getContainer()->get('app_payment_method.repository');
        $this->paymentHandlerRegistry = $this->getContainer()->get(PaymentHandlerRegistry::class);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');
        $appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, true, Context::createDefaultContext());
    }

    /**
     * @dataProvider paymentMethodDataProvider
     *
     * @param class-string<PaymentHandlerInterface> $handlerClass
     */
    public function testGetHandler(string $handlerName, string $handlerClass): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf($handlerClass, $handler);
    }

    /**
     * @param array<class-string<PaymentHandlerInterface>> $handlerInstances
     *
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetAsyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getAsyncPaymentHandler($paymentMethod->getId());

        if (\in_array(AsynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(AsynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface>> $handlerInstances
     *
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetSyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getSyncPaymentHandler($paymentMethod->getId());

        if (\in_array(SynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(SynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface>> $handlerInstances
     *
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetPreparedHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getPreparedPaymentHandler($paymentMethod->getId());

        if (\in_array(PreparedPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(PreparedPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface>> $handlerInstances
     *
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetRefundHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getRefundPaymentHandler($paymentMethod->getId());

        if (\in_array(RefundPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(RefundPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @dataProvider appPaymentMethodUrlProvider
     *
     * @param array<string, mixed> $appPaymentData
     * @param class-string<object> $expectedHandler
     */
    public function testAppResolve(array $appPaymentData, string $expectedHandler): void
    {
        $appPaymentData = \array_merge([
            'id' => Uuid::randomHex(),
            'identifier' => $expectedHandler,
            'appName' => $expectedHandler,
            'payUrl' => null,
            'finalizeUrl' => null,
            'validateUrl' => null,
            'captureUrl' => null,
            'refundUrl' => null,
        ], $appPaymentData);

        $paymentMethod = $this->getPaymentMethod('refundable');
        $appPaymentData['paymentMethodId'] = $paymentMethod->getId();

        $this->appPaymentMethodRepository->upsert([$appPaymentData], Context::createDefaultContext());

        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());

        static::assertInstanceOf($expectedHandler, $handler);
    }

    /**
     * @return array<string, array<string|class-string<PaymentHandlerInterface>|array<class-string<PaymentHandlerInterface>>>>
     */
    public static function paymentMethodDataProvider(): array
    {
        return [
            'app async' => [
                'app\\testPayments_async',
                AppAsyncPaymentHandler::class,
                [AsynchronousPaymentHandlerInterface::class],
            ],
            'app sync with payurl' => [
                'app\\testPayments_syncTracked',
                AppSyncPaymentHandler::class,
                [SynchronousPaymentHandlerInterface::class],
            ],
            'app sync' => [
                'app\\testPayments_sync',
                AppSyncPaymentHandler::class,
                [SynchronousPaymentHandlerInterface::class],
            ],
            'normal async' => [
                AsyncTestPaymentHandler::class,
                AsyncTestPaymentHandler::class,
                [AsynchronousPaymentHandlerInterface::class],
            ],
            'normal sync' => [
                InvoicePayment::class,
                InvoicePayment::class,
                [SynchronousPaymentHandlerInterface::class],
            ],
            'prepared' => [
                PreparedTestPaymentHandler::class,
                PreparedTestPaymentHandler::class,
                [PreparedPaymentHandlerInterface::class],
            ],
            'sync and prepared' => [
                MultipleTestPaymentHandler::class,
                MultipleTestPaymentHandler::class,
                [PreparedPaymentHandlerInterface::class, SynchronousPaymentHandlerInterface::class],
            ],
            'refund' => [
                RefundTestPaymentHandler::class,
                RefundTestPaymentHandler::class,
                [RefundPaymentHandlerInterface::class],
            ],
        ];
    }

    /**
     * @return array<array<array<string>|bool|string>>
     */
    public static function appPaymentMethodUrlProvider(): iterable
    {
        yield [[], AppSyncPaymentHandler::class];
        yield [['payUrl' => 'https://foo.bar/pay'], AppSyncPaymentHandler::class];
        yield [['finalizeUrl' => 'https://foo.bar/finalize'], AppAsyncPaymentHandler::class];
        yield [['payUrl' => 'https://foo.bar/pay', 'finalizeUrl' => 'https://foo.bar/finalize'], AppAsyncPaymentHandler::class];
        yield [['validateUrl' => 'https://foo.bar/validate', 'captureUrl' => 'https://foo.bar/capture'], AppPaymentHandler::class];
        yield [['refundUrl' => 'https://foo.bar/refund'], AppPaymentHandler::class];
        yield [['payUrl' => 'https://foo.bar/pay', 'finalizeUrl' => 'https://foo.bar/finalize', 'validateUrl' => 'https://foo.bar/validate', 'captureUrl' => 'https://foo.bar/capture', 'refundUrl' => 'https://foo.bar/refund'], AppPaymentHandler::class];
    }

    private function getPaymentMethod(string $handler): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $criteria->addAssociation('app');

        $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        if (!$method) {
            $method = [
                'id' => Uuid::randomHex(),
                'handlerIdentifier' => $handler,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => $handler,
                    ],
                ],
            ];

            $this->paymentMethodRepository->upsert([$method], Context::createDefaultContext());

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
            $criteria->addAssociation('app');

            $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();
        }

        return $method;
    }
}
