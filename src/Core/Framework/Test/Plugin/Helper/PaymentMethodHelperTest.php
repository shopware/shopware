<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Helper;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Plugin\Helper\PaymentMethodHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PaymentMethodHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const PLUGIN_NAME = 'SwagPaymentMethodTest';

    private const PAYMENT_METHOD_TECHNICAL_NAME = 'TestTechnicalName';

    private const PAYMENT_METHOD_ID = 'b8759d49b8a244ab8283f4a53f3e81aa';

    private const PAYMENT_METHOD_POSITION = 5;

    /**
     * @var RepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepo;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->pluginRepo = $this->getContainer()->get('plugin.repository');
        $this->paymentMethodRepo = $this->getContainer()->get('payment_method.repository');

        $this->context = Context::createDefaultContext();

        $this->pluginRepo->create(
            [
                [
                    'name' => self::PLUGIN_NAME,
                    'label' => 'foo',
                    'version' => '1.0.0',
                ],
            ],
            $this->context
        );
    }

    public function testSetPaymentMethodIsActiveById(): void
    {
        $helper = $this->createPaymentMethodHelper();
        $this->createPaymentMethod($helper);

        $helper->setPaymentMethodIsActiveById(true, self::PAYMENT_METHOD_ID, $this->context);

        $paymentMethodCollection = $this->paymentMethodRepo->read(new ReadCriteria([self::PAYMENT_METHOD_ID]), $this->context);
        /** @var PaymentMethodEntity $updatedPaymentMethod */
        $updatedPaymentMethod = $paymentMethodCollection->first();

        self::assertTrue($updatedPaymentMethod->getActive());
    }

    public function testSetPaymentMethodIsActiveByTechnicalName(): void
    {
        $helper = $this->createPaymentMethodHelper();
        $this->createPaymentMethod($helper, null);

        $helper->setPaymentMethodIsActiveByTechnicalName(true, self::PAYMENT_METHOD_TECHNICAL_NAME, $this->context);

        $paymentMethodCollection = $this->paymentMethodRepo->read(new ReadCriteria([self::PAYMENT_METHOD_ID]), $this->context);
        /** @var PaymentMethodEntity $updatedPaymentMethod */
        $updatedPaymentMethod = $paymentMethodCollection->first();

        self::assertTrue($updatedPaymentMethod->getActive());
    }

    private function createPaymentMethodHelper(): PaymentMethodHelper
    {
        return new PaymentMethodHelper(
            $this->pluginRepo,
            $this->paymentMethodRepo
        );
    }

    private function createPaymentMethod(PaymentMethodHelper $helper, ?int $position = self::PAYMENT_METHOD_POSITION): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(self::PAYMENT_METHOD_ID);
        $paymentMethod->setTechnicalName(self::PAYMENT_METHOD_TECHNICAL_NAME);
        $paymentMethod->setName('fooBar');
        if ($position !== null) {
            $paymentMethod->setPosition($position);
        }
        $helper->create(self::PLUGIN_NAME, $paymentMethod, $this->context);

        $paymentMethodCollection = $this->paymentMethodRepo->read(
            new ReadCriteria([self::PAYMENT_METHOD_ID]),
            $this->context
        );

        /** @var PaymentMethodEntity $createdPaymentMethod */
        $createdPaymentMethod = $paymentMethodCollection->first();

        self::assertSame(self::PAYMENT_METHOD_ID, $createdPaymentMethod->getId());
        self::assertSame(self::PAYMENT_METHOD_TECHNICAL_NAME, $createdPaymentMethod->getTechnicalName());
        if ($position !== null) {
            self::assertSame(self::PAYMENT_METHOD_POSITION, $createdPaymentMethod->getPosition());
        } else {
            self::assertSame(1, $createdPaymentMethod->getPosition());
        }
        self::assertFalse($createdPaymentMethod->getActive());
    }
}
