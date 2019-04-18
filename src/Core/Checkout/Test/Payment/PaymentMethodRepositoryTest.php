<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodRepositoryDecorator;
use Shopware\Core\Checkout\Payment\Exception\PluginPaymentMethodsDeleteRestrictionException;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\AsyncTestPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentMethodRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var PaymentMethodRepositoryDecorator
     */
    private $paymentRepository;

    /**
     * @var string
     */
    private $paymentMethodId;

    public function setUp(): void
    {
        $this->paymentRepository = $this->getContainer()->get('payment_method.repository');
        $this->paymentMethodId = Uuid::randomHex();
    }

    public function testCreatePaymentMethod(): void
    {
        $defaultContext = Context::createDefaultContext();

        $paymentMethod = $this->createPaymentMethodDummyArray();

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $criteria = new Criteria([$this->paymentMethodId]);
        $criteria->addAssociation('availabilityRule');

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        static::assertSame($this->paymentMethodId, $resultSet->first()->getId());
        static::assertSame(
            $paymentMethod[0]['availabilityRule']['id'],
            $resultSet->first()->getAvailabilityRule()->getId()
        );
        static::assertSame(
            'handler_shopware_asynctestpaymenthandler',
            $resultSet->first()->getFormattedHandlerIdentifier()
        );
    }

    public function testUpdatePaymentMethod(): void
    {
        $defaultContext = Context::createDefaultContext();

        $paymentMethod = $this->createPaymentMethodDummyArray();

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $updateParameter = [
            'id' => $this->paymentMethodId,
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'test update',
                'priority' => 5,
                'created_at' => new \DateTime(),
            ],
        ];

        $this->paymentRepository->update([$updateParameter], $defaultContext);

        $criteria = new Criteria([$this->paymentMethodId]);
        $criteria->addAssociation('availabilityRule');

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        static::assertSame('test update', $resultSet->first()->getAvailabilityRule()->getName());
    }

    public function testPaymentMethodCanBeDeleted(): void
    {
        $defaultContext = Context::createDefaultContext();

        $paymentMethod = $this->createPaymentMethodDummyArray();

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $primaryKey = [
            'id' => $this->paymentMethodId,
        ];

        $this->paymentRepository->delete([$primaryKey], $defaultContext);

        $criteria = new Criteria([$this->paymentMethodId]);

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        static::assertCount(0, $resultSet);
    }

    public function testPluginPaymentMethodCanNotBeDeleted(): void
    {
        $defaultContext = Context::createDefaultContext();
        $paymentMethod = $this->createPaymentMethodDummyArray();
        $paymentMethod[0]['pluginId'] = $this->addPlugin($defaultContext);

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $primaryKey = [
            'id' => $this->paymentMethodId,
        ];

        try {
            $this->paymentRepository->delete([$primaryKey], $defaultContext);
            static::fail('this should not be reached');
        } catch (PluginPaymentMethodsDeleteRestrictionException $e) {
        }

        $criteria = new Criteria([$this->paymentMethodId]);

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        static::assertCount(1, $resultSet);
    }

    public function testPluginPaymentMethodCanBeDeletedWithInternalDelete(): void
    {
        $defaultContext = Context::createDefaultContext();
        $paymentMethod = $this->createPaymentMethodDummyArray();
        $paymentMethod[0]['pluginId'] = $this->addPlugin($defaultContext);

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $primaryKey = [
            'id' => $this->paymentMethodId,
        ];

        $this->paymentRepository->internalDelete([$primaryKey], $defaultContext);

        $criteria = new Criteria([$this->paymentMethodId]);

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        static::assertCount(0, $resultSet);
    }

    public function testDefaultHandlerWrittenAtCreateIfNoHandlerIdentifierGiven(): void
    {
        $defaultContext = Context::createDefaultContext();
        $paymentMethod = $this->createPaymentMethodDummyArray();

        unset($paymentMethod[0]['handlerIdentifier']);

        $this->paymentRepository->create($paymentMethod, $defaultContext);

        $criteria = new Criteria([$this->paymentMethodId]);

        /** @var PaymentMethodCollection $resultSet */
        $resultSet = $this->paymentRepository->search($criteria, $defaultContext);

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $resultSet->filterByProperty('id', $this->paymentMethodId)
            ->getElements()[$this->paymentMethodId];

        static::assertSame(DefaultPayment::class, $paymentMethod->getHandlerIdentifier());
    }

    public function testThrowsExceptionIfNotAllRequiredValuesAreGiven(): void
    {
        $defaultContext = Context::createDefaultContext();
        $paymentMethod = $this->createPaymentMethodDummyArray();

        unset($paymentMethod[0]['name']);

        try {
            $this->paymentRepository->create($paymentMethod, $defaultContext);

            static::fail('The name should always be required!');
        } catch (WriteStackException $e) {
            static::assertStringContainsString('[propertyPath] => name', $e->getMessage());
            static::assertStringContainsString('[message] => This value should not be blank.', $e->getMessage());
        }
    }

    private function createPaymentMethodDummyArray(): array
    {
        return [
            [
                'id' => $this->paymentMethodId,
                'name' => 'test',
                'handlerIdentifier' => AsyncTestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
        ];
    }

    private function addPlugin(Context $context): string
    {
        $pluginId = Uuid::randomHex();

        /** @var EntityRepositoryInterface $pluginRepo */
        $pluginRepo = $this->getContainer()->get('plugin.repository');
        $pluginRepo->create([[
            'id' => $pluginId,
            'label' => 'testPlugin',
            'name' => 'testPluginName',
            'version' => 'version',
            'autoload' => [],
        ]], $context);

        return $pluginId;
    }
}
