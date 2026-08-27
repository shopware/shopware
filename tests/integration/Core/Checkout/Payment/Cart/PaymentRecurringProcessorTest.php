<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentRecurringProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<StateMachineHistoryCollection>
     */
    private EntityRepository $stateMachineHistoryRepository;

    private OrderTransactionStateHandler $stateHandler;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->stateMachineHistoryRepository = static::getContainer()->get('state_machine_history.repository');
        $this->stateHandler = static::getContainer()->get(OrderTransactionStateHandler::class);
    }

    /**
     * A payment service provider can confirm the payment through a webhook while the recurring handler is still
     * running, and the handler can fail afterwards anyway. The confirmation wins: the transaction keeps the state
     * the provider gave it, and the caller is told the renewal succeeded, because the money was collected.
     */
    #[DataProvider('confirmingTransitionProvider')]
    public function testAPaymentConfirmedWhileTheHandlerRunsIsNotFailed(\Closure $confirmPayment, string $confirmedState): void
    {
        $context = Context::createDefaultContext();
        $transactionId = $this->createOrderWithOpenTransaction();

        $processor = $this->createProcessor(
            $this->createHandlerConfirmingThenFailing($transactionId, $confirmPayment)
        );

        $processor->processRecurring($this->ids->get('10000'), $context);

        static::assertSame(
            $confirmedState,
            $this->getTransactionStateName($transactionId, $context),
            \sprintf('History: %s', implode(', ', $this->getTransitionHistory($transactionId, $context)))
        );

        static::assertNotContains(
            OrderTransactionStates::STATE_FAILED,
            $this->getTransitionHistory($transactionId, $context, targetStatesOnly: true),
            'The transaction must never have been failed'
        );
    }

    /**
     * @return iterable<string, array{\Closure(OrderTransactionStateHandler, string, Context): void, string}>
     */
    public static function confirmingTransitionProvider(): iterable
    {
        yield 'a captured payment keeps its state and the renewal counts as successful' => [
            static fn (OrderTransactionStateHandler $handler, string $id, Context $context) => $handler->paid($id, $context),
            OrderTransactionStates::STATE_PAID,
        ];

        yield 'an authorized payment is kept even though the state machine would allow failing it' => [
            static fn (OrderTransactionStateHandler $handler, string $id, Context $context) => $handler->authorize($id, $context),
            OrderTransactionStates::STATE_AUTHORIZED,
        ];
    }

    /**
     * Without a concurrent confirmation nothing changes: the transaction is failed and the handler error reaches
     * the caller, which is what marks the renewal as failed.
     */
    public function testAFailingHandlerStillFailsTheTransaction(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = $this->createOrderWithOpenTransaction();

        $processor = $this->createProcessor($this->createHandlerConfirmingThenFailing($transactionId, confirmPayment: null));

        try {
            $processor->processRecurring($this->ids->get('10000'), $context);
            static::fail('processRecurring() should rethrow the handler exception');
        } catch (PaymentException $e) {
            static::assertSame(PaymentException::PAYMENT_RECURRING_PROCESS_INTERRUPTED, $e->getErrorCode());
        }

        static::assertSame(OrderTransactionStates::STATE_FAILED, $this->getTransactionStateName($transactionId, $context));
    }

    /**
     * A transaction that reached a state which can neither be failed nor counts as confirmed - here cancelled -
     * must surface the handler error, not the state machine error about the impossible fail transition.
     */
    public function testTheHandlerErrorSurvivesAnImpossibleFailTransition(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = $this->createOrderWithOpenTransaction();

        $processor = $this->createProcessor($this->createHandlerConfirmingThenFailing(
            $transactionId,
            static fn (OrderTransactionStateHandler $handler, string $id, Context $context) => $handler->cancel($id, $context),
        ));

        try {
            $processor->processRecurring($this->ids->get('10000'), $context);
            static::fail('processRecurring() should rethrow the handler exception');
        } catch (PaymentException $e) {
            static::assertSame(PaymentException::PAYMENT_RECURRING_PROCESS_INTERRUPTED, $e->getErrorCode());
        }

        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $this->getTransactionStateName($transactionId, $context));
    }

    private function createOrderWithOpenTransaction(): string
    {
        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction', state: OrderTransactionStates::STATE_OPEN))
            ->add('paymentMethod', [
                'id' => $this->ids->get('payment_method'),
                'technicalName' => 'payment_test_' . $this->ids->get('payment_method'),
                'handlerIdentifier' => AbstractPaymentHandler::class,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'foo',
                    ],
                ],
            ])
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        return $this->ids->get('transaction');
    }

    /**
     * Stands in for a handler that moves the transaction to unconfirmed, has the payment confirmed underneath it by
     * a provider webhook, and then fails anyway - the interleaving the provider webhook creates in production.
     */
    private function createHandlerConfirmingThenFailing(string $transactionId, ?\Closure $confirmPayment): AbstractPaymentHandler
    {
        return new class($this->stateHandler, $transactionId, $confirmPayment) extends AbstractPaymentHandler {
            public function __construct(
                private readonly OrderTransactionStateHandler $stateHandler,
                private readonly string $transactionId,
                private readonly ?\Closure $confirmPayment,
            ) {
            }

            public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
            {
                return $type === PaymentHandlerType::RECURRING;
            }

            public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct): ?RedirectResponse
            {
                return null;
            }

            public function recurring(PaymentTransactionStruct $transaction, Context $context): void
            {
                $this->stateHandler->processUnconfirmed($this->transactionId, $context);

                if ($this->confirmPayment !== null) {
                    ($this->confirmPayment)($this->stateHandler, $this->transactionId, $context);
                }

                throw PaymentException::recurringInterrupted($this->transactionId, 'recurring capture failed');
            }
        };
    }

    private function createProcessor(AbstractPaymentHandler $handler): PaymentRecurringProcessor
    {
        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry->method('getPaymentMethodHandler')->willReturn($handler);

        return new PaymentRecurringProcessor(
            $this->orderTransactionRepository,
            static::getContainer()->get(InitialStateIdLoader::class),
            $this->stateHandler,
            $registry,
            static::getContainer()->get(PaymentTransactionStructFactory::class),
            new NullLogger(),
        );
    }

    private function getTransactionStateName(string $transactionId, Context $context): string
    {
        $criteria = (new Criteria([$transactionId]))->addAssociation('stateMachineState');

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($transaction);
        static::assertNotNull($transaction->getStateMachineState());

        return $transaction->getStateMachineState()->getTechnicalName();
    }

    /**
     * @return list<string>
     */
    private function getTransitionHistory(string $transactionId, Context $context, bool $targetStatesOnly = false): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('referencedId', $transactionId))
            ->addAssociation('fromStateMachineState')
            ->addAssociation('toStateMachineState')
            ->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        $history = [];
        foreach ($this->stateMachineHistoryRepository->search($criteria, $context)->getEntities() as $entry) {
            $toState = $entry->getToStateMachineState()?->getTechnicalName() ?? '?';

            $history[] = $targetStatesOnly ? $toState : \sprintf(
                '%s -> %s',
                $entry->getFromStateMachineState()?->getTechnicalName() ?? '?',
                $toState,
            );
        }

        return $history;
    }
}
