<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Handler;

use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SyncPayPayload;
use Shopware\Core\Framework\App\Payment\Response\SyncPayResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppSyncPaymentHandler extends AppPaymentHandler implements SynchronousPaymentHandlerInterface
{
    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $payUrl = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getPayUrl();
        if (empty($payUrl)) {
            return;
        }

        $payload = $this->buildPayload($transaction);
        $app = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getApp();
        if ($app === null) {
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'App not defined');
        }

        try {
            $response = $this->payloadService->request($payUrl, $payload, $app, SyncPayResponse::class, $salesChannelContext->getContext());
        } catch (ClientExceptionInterface $exception) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof SyncPayResponse) {
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'Invalid app response');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), $response->getMessage() ?? 'Payment was reported as failed.');
        }

        if (empty($response->getStatus())) {
            return;
        }

        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $transaction->getOrderTransaction()->getId(),
                $response->getStatus(),
                'stateId'
            ),
            $salesChannelContext->getContext()
        );
    }

    private function buildPayload(SyncPaymentTransactionStruct $transaction): SyncPayPayload
    {
        return new SyncPayPayload(
            $transaction->getOrderTransaction(),
            $transaction->getOrder()
        );
    }
}
