<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\MultipleTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\PreparedTestPaymentHandler;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Payment\Handler\AppAsyncPaymentHandler;
use Shopware\Core\Framework\App\Payment\Handler\AppSyncPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;

class PaymentHandlerRegistryTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private PaymentHandlerRegistry $paymentHandlerRegistry;

    private EntityRepositoryInterface $paymentMethodRepository;

    public function setUp(): void
    {
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
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
        $handler = $this->paymentHandlerRegistry->getHandlerForPaymentMethod($paymentMethod);
        static::assertInstanceOf($handlerClass, $handler);
    }

    /**
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetAsyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getAsyncHandlerForPaymentMethod($paymentMethod);

        if (\in_array(AsynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(AsynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetSyncHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getSyncHandlerForPaymentMethod($paymentMethod);

        if (\in_array(SynchronousPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(SynchronousPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    /**
     * @dataProvider paymentMethodDataProvider
     */
    public function testGetPreparedHandler(string $handlerName, string $handlerClass, array $handlerInstances): void
    {
        $paymentMethod = $this->getPaymentMethod($handlerName);
        $handler = $this->paymentHandlerRegistry->getPreparedHandlerForPaymentMethod($paymentMethod);

        if (\in_array(PreparedPaymentHandlerInterface::class, $handlerInstances, true)) {
            static::assertInstanceOf(PreparedPaymentHandlerInterface::class, $handler);
        } else {
            static::assertNull($handler);
        }
    }

    public function paymentMethodDataProvider(): array
    {
        return [
            'app async' => [
                'app\\testPayments_async',
                AppAsyncPaymentHandler::class,
                [PreparedPaymentHandlerInterface::class, AsynchronousPaymentHandlerInterface::class],
            ],
            'app sync with payurl' => [
                'app\\testPayments_syncTracked',
                AppSyncPaymentHandler::class,
                [PreparedPaymentHandlerInterface::class, SynchronousPaymentHandlerInterface::class],
            ],
            'app sync' => [
                'app\\testPayments_sync',
                AppSyncPaymentHandler::class,
                [PreparedPaymentHandlerInterface::class, SynchronousPaymentHandlerInterface::class],
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
        ];
    }

    private function getPaymentMethod(string $handler): PaymentMethodEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $handler));
        $criteria->addAssociation('app');

        $method = $this->paymentMethodRepository->search($criteria, Context::createDefaultContext())->first();

        if ($method === null) {
            $method = new PaymentMethodEntity();
            $method->setHandlerIdentifier($handler);
        }

        return $method;
    }
}
