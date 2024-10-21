<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\PaymentHandler\AsyncTestPaymentHandler;
use Shopware\Core\Test\Integration\PaymentHandler\MultipleTestPaymentHandler;
use Shopware\Core\Test\Integration\PaymentHandler\PreparedTestPaymentHandler;
use Shopware\Core\Test\Integration\PaymentHandler\RefundTestPaymentHandler;
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

    public function testGetHandler(): void
    {
        $paymentMethod = $this->getPaymentMethod(InvoicePayment::class);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf(InvoicePayment::class, $handler);
    }

    /**
     * @param class-string<AbstractPaymentHandler> $handlerClass
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    #[DataProvider('paymentMethodDataProvider')]
    public function testGetHandlerOld(string $handlerName, string $handlerClass): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf($handlerClass, $handler);
    }

    /**
     * @param array<class-string<PaymentHandlerInterface|AbstractPaymentHandler>> $handlerInstances
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    #[DataProvider('paymentMethodDataProvider')]
    public function testGetAsyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getAsyncPaymentHandler($paymentMethod->getId());

        if (\in_array(AsynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(AsynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface|AbstractPaymentHandler>> $handlerInstances
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    #[DataProvider('paymentMethodDataProvider')]
    public function testGetSyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getSyncPaymentHandler($paymentMethod->getId());

        if (\in_array(SynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(SynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface|AbstractPaymentHandler>> $handlerInstances
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    #[DataProvider('paymentMethodDataProvider')]
    public function testGetPreparedHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getPreparedPaymentHandler($paymentMethod->getId());

        if (\in_array(PreparedPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(PreparedPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @param array<class-string<PaymentHandlerInterface|AbstractPaymentHandler>> $handlerInstances
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    #[DataProvider('paymentMethodDataProvider')]
    public function testGetRefundHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getRefundPaymentHandler($paymentMethod->getId());

        if (\in_array(RefundPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(RefundPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    public function testAppResolve(): void
    {
        $appPaymentData = [
            'id' => Uuid::randomHex(),
            'identifier' => 'apptest',
            'appName' => 'apptest',
            'payUrl' => null,
            'finalizeUrl' => null,
            'validateUrl' => null,
            'captureUrl' => null,
            'refundUrl' => null,
        ];

        $paymentMethod = $this->getPaymentMethod('refundable');
        $appPaymentData['paymentMethodId'] = $paymentMethod->getId();

        $this->appPaymentMethodRepository->upsert([$appPaymentData], Context::createDefaultContext());

        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());

        static::assertInstanceOf(AppPaymentHandler::class, $handler);
    }

    /**
     * @return array<string, array<string|class-string<PaymentHandlerInterface|AbstractPaymentHandler>|array<class-string<PaymentHandlerInterface|AbstractPaymentHandler>>>>
     *
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    public static function paymentMethodDataProvider(): array
    {
        return [
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

    private function getPaymentMethod(string $handler): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $criteria->addAssociation('app');

        /** @var PaymentMethodEntity|null $method */
        $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        if (!$method) {
            $method = [
                'id' => Uuid::randomHex(),
                'technicalName' => 'payment_test',
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

            /** @var PaymentMethodEntity|null $method */
            $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();
        }

        static::assertNotNull($method);

        return $method;
    }
}
