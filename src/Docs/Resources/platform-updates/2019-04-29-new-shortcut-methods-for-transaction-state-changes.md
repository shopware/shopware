[titleEn]: <>(New shortcut methods for transaction state changes)

There is now a new class which can help you to deal with the `StateMachine`.
`Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler` contains some methods to improve the readability of your code when changing the state.


old way:
```
public function example()
{
    $completeStateId = $this->stateMachineRegistry->getStateByTechnicalName(
        OrderTransactionStates::STATE_MACHINE,
        OrderTransactionStates::STATE_PAID,
        $context
    )->getId();

    $data = [
        'id' => $transaction->getOrderTransaction()->getId(),
        'stateId' => $completeStateId,
    ];

    $this->transactionRepository->update([$data], $context);
}
```

new way:
```
public function example()
{
    $transactionStateHandler = $this->getContainer()->get(OrderTransactionStateHandler::class);
    $transactionStateHandler->complete($transaction->getOrderTransaction()->getId(), $context);
}
```
