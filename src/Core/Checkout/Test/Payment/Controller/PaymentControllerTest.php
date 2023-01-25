<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler as AsyncTestPaymentHandlerV630;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private JWTFactoryV2 $tokenFactory;

    private EntityRepository $orderRepository;

    private EntityRepository $orderTransactionRepository;

    private EntityRepository $paymentMethodRepository;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenFactory = $this->getContainer()->get(JWTFactoryV2::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->paymentService = $this->getContainer()->get(PaymentService::class);
    }

    public function testCallWithoutToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
    }

    public function testCallWithInvalidToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=abc');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValidTokenWithInvalidOrder(): void
    {
        $client = $this->getBrowser();

        $tokenStruct = new TokenStruct(null, null, Uuid::randomHex(), Uuid::randomHex(), 'testFinishUrl');
        $token = $this->tokenFactory->generateToken($tokenStruct);

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValid(): void
    {
        $transaction = $this->createValidOrderTransaction();

        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl');
        $token = $this->tokenFactory->generateToken($tokenStruct);

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testFinishUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    public function testCancelledPayment(): void
    {
        $transaction = $this->createValidOrderTransaction();

        $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl', null, 'testErrorUrl');
        $token = $this->tokenFactory->generateToken($tokenStruct);

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token . '&cancel=1');

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testErrorUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    private function getBrowser(): KernelBrowser
    {
        return KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
    }

    private function getSalesChannelContext(string $paymentMethodId): SalesChannelContext
    {
        return $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]);
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::randomHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(Context $context): string
    {
        $orderId = Uuid::randomHex();

        $order = $this->getOrderData($orderId, $context);
        $this->orderRepository->upsert($order, $context);

        return $orderId;
    }

    private function createPaymentMethodV630(
        Context $context,
        string $handlerIdentifier = AsyncTestPaymentHandlerV630::class
    ): string {
        $id = Uuid::randomHex();
        $payment = [
            'id' => $id,
            'handlerIdentifier' => $handlerIdentifier,
            'name' => 'Test Payment',
            'description' => 'Test payment handler',
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$payment], $context);

        return $id;
    }

    private function createValidOrderTransaction(): OrderTransactionEntity
    {
        $context = Context::createDefaultContext();

        $paymentMethodId = $this->createPaymentMethodV630($context);
        $orderId = $this->createOrder($context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(), $salesChannelContext);

        static::assertNotNull($response);
        static::assertEquals(AsyncTestPaymentHandlerV630::REDIRECT_URL, $response->getTargetUrl());

        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);
        $transaction->setStateId(Uuid::randomHex());

        return $transaction;
    }
}
