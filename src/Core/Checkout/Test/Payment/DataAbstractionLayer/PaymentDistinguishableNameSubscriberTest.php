<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber;
use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal (flag:FEATURE_NEXT_15170)
 */
class PaymentDistinguishableNameSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentDistinguishableNameSubscriber $subscriber;

    private Context $context;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_15170', $this);

        $this->subscriber = new PaymentDistinguishableNameSubscriber($this->getContainer()->get('payment_method.repository'));
        $this->context = Context::createDefaultContext();
    }

    public function testSubscribedEvents(): void
    {
        static::assertEquals(
            [
                PaymentEvents::PAYMENT_METHOD_TRANSLATION_WRITTEN_EVENT => 'generateDistinguishablePaymentNames',
                PaymentEvents::PAYMENT_METHOD_TRANSLATION_DELETED_EVENT => 'removeDistinguishablePaymentNames',
                PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'addDistinguishablePaymentName',
            ],
            $this->subscriber::getSubscribedEvents()
        );
    }

    public function testGeneratesDistinguishablePaymentNameIfApplicable(): void
    {
        $paymentRepository = $this->getContainer()->get('payment_method.repository');

        $paymentRepository->create(
            [
                [
                    'id' => $invoicePaymentByShopwarePluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnungskauf',
                    ],
                    'active' => true,
                    'plugin' => [
                        'name' => 'Shopware',
                        'baseClass' => 'Swag\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Shopware',
                            'de-DE' => 'Shopware',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByPluginId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnung',
                    ],
                    'active' => true,
                    'plugin' => [
                        'name' => 'Plugin',
                        'baseClass' => 'Plugin\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Plugin',
                            'de-DE' => 'deutsches Plugin',
                        ],
                    ],
                ],
                [
                    'id' => $invoicePaymentByAppId = Uuid::randomHex(),
                    'name' => [
                        'en-GB' => 'Invoice',
                        'de-DE' => 'Rechnung',
                    ],
                    'active' => true,
                    'appPaymentMethod' => [
                        'identifier' => 'identifier',
                        'appName' => 'appName',
                        'app' => [
                            'name' => 'App',
                            'path' => 'path',
                            'version' => '1.0.0',
                            'label' => 'App',
                            'integration' => [
                                'accessKey' => 'accessKey',
                                'secretAccessKey' => 'secretAccessKey',
                                'label' => 'Integration',
                            ],
                            'aclRole' => [
                                'name' => 'aclRole',
                            ],
                        ],
                    ],
                ],
            ],
            $this->context
        );

        $payments = $paymentRepository
            ->search(new Criteria(), $this->context)
            ->getEntities();

        /** @var PaymentMethodEntity $invoicePaymentByShopwarePlugin */
        $invoicePaymentByShopwarePlugin = $payments->get($invoicePaymentByShopwarePluginId);
        static::assertEquals('Invoice | Shopware', $invoicePaymentByShopwarePlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertEquals('Invoice | Plugin', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertEquals('Invoice | App', $invoicePaymentByApp->getDistinguishableName());

        /** @var PaymentMethodEntity $paidInAdvance */
        $paidInAdvance = $payments
            ->filterByProperty('name', 'Paid in advance')
            ->first();

        static::assertEquals($paidInAdvance->getTranslation('name'), $paidInAdvance->getTranslation('distinguishableName'));

        $germanContext = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]
        );

        $payments = $paymentRepository
            ->search(new Criteria(), $germanContext)
            ->getEntities();

        /** @var PaymentMethodEntity $invoicePaymentByShopwarePlugin */
        $invoicePaymentByShopwarePlugin = $payments->get($invoicePaymentByShopwarePluginId);
        static::assertEquals('Rechnungskauf', $invoicePaymentByShopwarePlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByPlugin */
        $invoicePaymentByPlugin = $payments->get($invoicePaymentByPluginId);
        static::assertEquals('Rechnung | deutsches Plugin', $invoicePaymentByPlugin->getDistinguishableName());

        /** @var PaymentMethodEntity $invoicePaymentByApp */
        $invoicePaymentByApp = $payments->get($invoicePaymentByAppId);
        static::assertEquals('Rechnung | App', $invoicePaymentByApp->getDistinguishableName());
    }

    public function testItDoesNotGenerateDistinguishableNameIfPaymentNameIsUnique(): void
    {
        $paymentRepository = $this->getContainer()->get('payment_method.repository');

        $paymentRepository->create(
            [
                [
                    'id' => $invoicePaymentId = Uuid::randomHex(),
                    'name' => 'Invoice Test',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Shopware',
                        'baseClass' => 'Swag\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Shopware',
                            'de-DE' => 'Shopware',
                        ],
                    ],
                ],
                [
                    'id' => $creditPaymentId = Uuid::randomHex(),
                    'name' => 'Credit',
                    'active' => true,
                    'plugin' => [
                        'name' => 'Plugin',
                        'baseClass' => 'Plugin\Paypal',
                        'autoload' => [],
                        'version' => '1.0.0',
                        'label' => [
                            'en-GB' => 'Plugin',
                            'de-DE' => 'deutsches Plugin',
                        ],
                    ],
                ],
            ],
            $this->context
        );

        $payments = $paymentRepository
            ->search(new Criteria(), $this->context)
            ->getEntities();

        /** @var PaymentMethodEntity $invoicePayment */
        $invoicePayment = $payments->get($invoicePaymentId);
        static::assertEquals('Invoice Test', $invoicePayment->getDistinguishableName());

        /** @var PaymentMethodEntity $creditPayment */
        $creditPayment = $payments->get($creditPaymentId);
        static::assertEquals('Credit', $creditPayment->getDistinguishableName());
    }
}
