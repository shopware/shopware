<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Handler;

use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\App\Payment\Payload\Struct\AsyncFinalizePayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\AsyncPayPayload;
use Shopware\Core\Framework\App\Payment\Response\AsyncFinalizeResponse;
use Shopware\Core\Framework\App\Payment\Response\AsyncPayResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppAsyncPaymentHandler extends AppPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $this->transactionStateHandler->processUnconfirmed($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());

        $requestData = $dataBag->all();
        unset($requestData['_csrf_token']);

        $payload = $this->buildPayPayload($transaction, $requestData);
        $app = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getApp();
        if ($app === null) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'App not defined');
        }

        $url = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getPayUrl();
        if ($url === null) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), 'Pay URL not defined');
        }

        try {
            $response = $this->payloadService->request($url, $payload, $app, AsyncPayResponse::class, $salesChannelContext->getContext());
        } catch (ClientExceptionInterface $exception) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof AsyncPayResponse) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'Invalid app response');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw new AsyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'Error during payment initialization: ' . $response->getMessage());
        }

        if ($response->getStatus() !== StateMachineTransitionActions::ACTION_PROCESS_UNCONFIRMED) {
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

        return new RedirectResponse($response->getRedirectUrl());
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        $queryParameters = $request->query->all();
        unset($queryParameters['_sw_payment_token']);

        $payload = $this->buildFinalizePayload($transaction, $queryParameters);
        $app = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getApp();
        if ($app === null) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), 'App not defined');
        }

        $url = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getFinalizeUrl();
        if ($url === null) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), 'Finalize URL not defined');
        }

        try {
            $response = $this->payloadService->request($url, $payload, $app, AsyncFinalizeResponse::class, $salesChannelContext->getContext());
        } catch (ClientExceptionInterface $exception) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof AsyncFinalizeResponse) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), 'Invalid app response');
        }

        if ($response->getStatus() === StateMachineTransitionActions::ACTION_CANCEL) {
            throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId(), $response->getMessage() ?? '');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), $response->getMessage() ?? 'Payment was reported as failed.');
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

    /**
     * @param array<string|int, mixed> $requestData
     */
    private function buildPayPayload(AsyncPaymentTransactionStruct $transaction, array $requestData): AsyncPayPayload
    {
        return new AsyncPayPayload(
            $transaction->getOrderTransaction(),
            $transaction->getOrder(),
            $transaction->getReturnUrl(),
            $requestData
        );
    }

    /**
     * @param array<string|int, mixed> $queryParameters
     */
    private function buildFinalizePayload(AsyncPaymentTransactionStruct $transaction, array $queryParameters): AsyncFinalizePayload
    {
        return new AsyncFinalizePayload(
            $transaction->getOrderTransaction(),
            $queryParameters
        );
    }
}
