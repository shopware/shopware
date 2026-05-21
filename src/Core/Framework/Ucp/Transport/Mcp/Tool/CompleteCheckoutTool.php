<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutMapper;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatus;
use Shopware\Core\Framework\Ucp\Capability\Checkout\GuestCustomerProvisioner;
use Shopware\Core\Framework\Ucp\Event\UcpCheckoutRequestEvent;
use Shopware\Core\Framework\Ucp\Event\UcpCheckoutResponseEvent;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpEvents;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'complete_checkout', capability: CheckoutCapability::NAME, description: 'Complete a checkout (place the order)')]
#[Package('framework')]
class CompleteCheckoutTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        private readonly CheckoutMapper $checkoutMapper,
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?GuestCustomerProvisioner $guestProvisioner = null,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'string'],
                'payment' => [
                    'type' => 'object',
                    'properties' => [
                        'instruments' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'handler_id' => ['type' => 'string'],
                                    'credential' => ['type' => 'object'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('checkout.json', 'checkout_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $sc = $context->salesChannelContext;
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('complete_checkout', 'checkout id required');
        }

        // Dispatch the request event first — extensions (notably AP2) MUST
        // get a chance to verify mandates before we touch state. This used
        // to be skipped for MCP, bypassing AP2 entirely.
        $requestEvent = new UcpCheckoutRequestEvent($id, $arguments, $sc, $context);
        $this->eventDispatcher->dispatch($requestEvent, UcpEvents::CHECKOUT_REQUEST);
        if ($requestEvent->isRejected()) {
            $rejection = $requestEvent->getRejection() ?? [
                'code' => 'rejected',
                'content' => 'Request rejected by an extension',
            ];
            throw UcpException::featureDisabled();
        }

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $id);
        $request->attributes->set('token', $id);
        $request->query->set('token', $id);
        $request->request = new InputBag();

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        if ($sc->getCustomer() === null && $this->guestProvisioner !== null) {
            $buyer = \is_array($arguments['buyer'] ?? null) ? $arguments['buyer'] : [];
            $sc = $this->guestProvisioner->provisionIfMissing($sc, $buyer, $id);
        }

        $orderData = new RequestDataBag();
        $orderData->set('customFields', ['ucp_checkout_id' => $id]);
        $instruments = $arguments['payment']['instruments'] ?? [];
        if (\is_array($instruments) && $instruments !== []) {
            $first = $instruments[0];
            $handlerId = $first['handler_id'] ?? null;
            $handler = \is_string($handlerId) ? $this->paymentHandlerRegistry->get($handlerId) : null;
            if ($handler === null) {
                $allHandlers = $this->paymentHandlerRegistry->all();
                $handler = $allHandlers !== [] ? reset($allHandlers) : null;
            }
            if ($handler !== null) {
                $prepared = $handler->prepareInstrument($first, $sc);
                $orderData->set('payment_method_id', $prepared['paymentMethodId']);
                $orderData->set('ucp_payment_token', $prepared['token']);
            }
        }

        $orderResponse = $this->cartOrderRoute->order($loaded, $sc, $orderData);

        // Re-load the cart so totals/line items reflect the placed order
        // rather than the pre-order snapshot the mapper would otherwise see.
        $finalCart = $this->cartLoadRoute->load($request, $sc)->getCart();
        $response = $this->checkoutMapper->toResponse($finalCart, $sc, $context->config, true, $context, $arguments);
        $response['order_id'] = $orderResponse->getOrder()->getId();
        $response['status'] = CheckoutStatus::COMPLETED;

        // Dispatch response event for extensions (e.g. AP2 checkout_signature).
        $responseEvent = new UcpCheckoutResponseEvent($id, $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);

        return $responseEvent->getResponse();
    }
}
