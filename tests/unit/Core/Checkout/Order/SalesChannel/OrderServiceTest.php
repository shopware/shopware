<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\Validation\OrderValidationFactory;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderService::class)]
class OrderServiceTest extends TestCase
{
    private Stub&CartService $cartService;

    /**
     * @var Stub&EntityRepository<PaymentMethodCollection>
     */
    private Stub&EntityRepository $paymentMethodRepository;

    private Stub&StateMachineRegistry $stateMachineRegistry;

    protected function setUp(): void
    {
        $this->cartService = static::createStub(CartService::class);
        $this->paymentMethodRepository = static::createStub(EntityRepository::class);
        $this->stateMachineRegistry = static::createStub(StateMachineRegistry::class);
    }

    public function testCreateOrderWithDigitalGoodsNeedsRevocationConfirm(): void
    {
        $dataBag = new DataBag();
        $dataBag->set('tos', true);
        $context = static::createStub(SalesChannelContext::class);

        $cart = new Cart('test');
        $lineItem = (new LineItem('a', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_PHYSICAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_PHYSICAL]);
        }

        $cart->add($lineItem);
        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);
        $cartService->expects($this->exactly(2))->method('order');
        $orderService = $this->buildOrderService($cartService);

        $idSearchResult = new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext());
        $this->paymentMethodRepository->method('searchIds')->willReturn($idSearchResult);

        $orderService->createOrder($dataBag, $context);

        $lineItem = (new LineItem('b', 'test'))->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL);

        if (!Feature::isActive('v6.8.0.0')) {
            $lineItem->setStates([State::IS_DOWNLOAD]);
        }
        $cart->add($lineItem);

        try {
            $orderService->createOrder($dataBag, $context);

            static::fail('Did not throw exception');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(ConstraintViolationException::class, $exception);
            $errors = iterator_to_array($exception->getErrors());
            static::assertCount(1, $errors);
            static::assertSame('VIOLATION::IS_BLANK_ERROR', $errors[0]['code']);
            static::assertSame('/revocation', $errors[0]['source']['pointer']);
        }

        $dataBag->set('revocation', true);

        $orderService->createOrder($dataBag, $context);
    }

    public function testOrderStateTransitionUseData(): void
    {
        $context = Context::createDefaultContext();
        $orderId = Uuid::randomHex();
        $data = new ParameterBag([
            'stateFieldName' => 'abc',
            'internalComment' => 'def',
        ]);

        $stateMachineStates = new StateMachineStateCollection();
        $toPlace = new StateMachineStateEntity();
        $stateMachineStates->set('toPlace', $toPlace);

        $expectedTransition = new Transition('order', $orderId, 'cancel', 'abc', 'def');

        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with(
                static::equalTo($expectedTransition),
                $context
            )
            ->willReturn($stateMachineStates);
        $orderService = $this->buildOrderService(null, $stateMachineRegistry);

        $state = $orderService->orderStateTransition($orderId, 'cancel', $data, $context);
        static::assertSame($toPlace, $state);
    }

    public function testOrderTransactionStateTransitionUseData(): void
    {
        $context = Context::createDefaultContext();
        $orderTransactionId = Uuid::randomHex();
        $data = new ParameterBag([
            'stateFieldName' => 'abc',
            'internalComment' => 'def',
        ]);

        $stateMachineStates = new StateMachineStateCollection();
        $toPlace = new StateMachineStateEntity();
        $stateMachineStates->set('toPlace', $toPlace);

        $expectedTransition = new Transition('order_transaction', $orderTransactionId, 'cancel', 'abc', 'def');

        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with(
                static::equalTo($expectedTransition),
                $context
            )
            ->willReturn($stateMachineStates);
        $orderService = $this->buildOrderService(null, $stateMachineRegistry);

        $state = $orderService->orderTransactionStateTransition($orderTransactionId, 'cancel', $data, $context);
        static::assertSame($toPlace, $state);
    }

    public function testOrderDeliveryStateTransitionUseData(): void
    {
        $context = Context::createDefaultContext();
        $orderDeliveryId = Uuid::randomHex();
        $data = new ParameterBag([
            'stateFieldName' => 'abc',
            'internalComment' => 'def',
        ]);

        $stateMachineStates = new StateMachineStateCollection();
        $toPlace = new StateMachineStateEntity();
        $stateMachineStates->set('toPlace', $toPlace);

        $expectedTransition = new Transition('order_delivery', $orderDeliveryId, 'cancel', 'abc', 'def');

        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);
        $stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with(
                static::equalTo($expectedTransition),
                $context
            )
            ->willReturn($stateMachineStates);
        $orderService = $this->buildOrderService(null, $stateMachineRegistry);

        $state = $orderService->orderDeliveryStateTransition($orderDeliveryId, 'cancel', $data, $context);
        static::assertSame($toPlace, $state);
    }

    private function buildOrderService(
        ?CartService $cartService = null,
        ?StateMachineRegistry $stateMachineRegistry = null,
    ): OrderService {
        $systemConfigService = static::createStub(SystemConfigService::class);

        return new OrderService(
            new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            new OrderValidationFactory($systemConfigService),
            static::createStub(EventDispatcherInterface::class),
            $cartService ?? $this->cartService,
            $this->paymentMethodRepository,
            $stateMachineRegistry ?? $this->stateMachineRegistry,
        );
    }
}
