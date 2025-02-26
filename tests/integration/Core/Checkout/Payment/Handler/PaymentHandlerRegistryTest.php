<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\Handler;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
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
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->appPaymentMethodRepository = static::getContainer()->get('app_payment_method.repository');
        $this->paymentHandlerRegistry = static::getContainer()->get(PaymentHandlerRegistry::class);

        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testPayments/manifest.xml');
        $appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $appLifecycle->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
    }

    public function testGetHandler(): void
    {
        $paymentMethod = $this->getPaymentMethod(InvoicePayment::class);
        $handler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        static::assertInstanceOf(InvoicePayment::class, $handler);
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
