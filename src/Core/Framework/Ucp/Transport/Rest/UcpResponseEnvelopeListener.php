<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Rest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogLookupCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogSearchCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\ContinueUrlBuilder;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderCapability;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Ensures every UCP response carries the envelope per overview.md §"Response
 * Envelope". The envelope is **operation-filtered**: each route advertises
 * only the capabilities it actually exercises, not the full negotiated set.
 *
 *   {
 *     "ucp": {
 *       "version": "2026-01-23",
 *       "capabilities": { "dev.ucp.shopping.cart": [...] },  // op-relevant only
 *       "payment_handlers": { ... }   // on checkout routes only
 *     },
 *     ...
 *   }
 *
 * For error responses (4xx/5xx) the envelope additionally includes
 * `continue_url` so platforms can hand the buyer off to the storefront
 * for recovery (overview.md §"Error Handling").
 *
 * @internal
 */
#[Package('framework')]
class UcpResponseEnvelopeListener implements EventSubscriberInterface
{
    /**
     * Route-name → capabilities the route operates on.
     */
    private const ROUTE_CAPABILITIES = [
        'ucp.catalog.search' => [CatalogSearchCapability::NAME],
        'ucp.catalog.lookup' => [CatalogLookupCapability::NAME],
        'ucp.cart.create' => [CartCapability::NAME],
        'ucp.cart.read' => [CartCapability::NAME],
        'ucp.cart.update' => [CartCapability::NAME],
        'ucp.cart.cancel' => [CartCapability::NAME],
        'ucp.cart.discount.apply' => [CartCapability::NAME, 'dev.ucp.shopping.discount'],
        'ucp.checkout.create' => [CheckoutCapability::NAME],
        'ucp.checkout.read' => [CheckoutCapability::NAME],
        'ucp.checkout.update' => [CheckoutCapability::NAME],
        'ucp.checkout.complete' => [CheckoutCapability::NAME, OrderCapability::NAME],
        'ucp.checkout.cancel' => [CheckoutCapability::NAME],
        'ucp.order.read' => [OrderCapability::NAME],
        'ucp.order.read.conformance' => [OrderCapability::NAME],
        'ucp.order.update' => [OrderCapability::NAME],
        'ucp.order.update.conformance' => [OrderCapability::NAME],
    ];

    /**
     * Routes that include `ucp.payment_handlers` in the envelope.
     */
    private const CHECKOUT_ROUTES = [
        'ucp.checkout.create',
        'ucp.checkout.read',
        'ucp.checkout.update',
        'ucp.checkout.complete',
        'ucp.checkout.cancel',
    ];

    public function __construct(
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly ContinueUrlBuilder $continueUrlBuilder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            return;
        }
        $response = $event->getResponse();
        if (!$response instanceof JsonResponse) {
            return;
        }

        $content = $response->getContent();
        if (!\is_string($content) || $content === '') {
            return;
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded)) {
            return;
        }

        $routeName = (string) $request->attributes->get('_route');
        $sc = $context->salesChannelContext;

        $envelope = [
            'version' => $context->intersection->protocolVersion,
            'capabilities' => $this->capabilitiesForOperation($routeName, $context),
        ];

        if (\in_array($routeName, self::CHECKOUT_ROUTES, true)) {
            $envelope['payment_handlers'] = $this->paymentHandlerRegistry->describeForSalesChannel(
                $sc->getSalesChannelId(),
                $sc->getContext()
            );
        }

        // Error / non-2xx — surface a recovery URL where applicable.
        if ($response->getStatusCode() >= 400) {
            $envelope['status'] = 'error';
            $continueUrl = $this->continueUrlBuilder->buildForRecovery($context->config, $sc);
            if ($continueUrl !== null) {
                $decoded['continue_url'] ??= $continueUrl;
            }
        }

        if (isset($decoded['ucp']) && \is_array($decoded['ucp'])) {
            $decoded['ucp'] = array_merge($decoded['ucp'], $envelope);
        } else {
            $decoded = ['ucp' => $envelope] + $decoded;
        }

        $response->setData($decoded);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function capabilitiesForOperation(string $routeName, UcpRequestContext $context): array
    {
        $negotiated = $context->intersection->toArray();
        $relevant = self::ROUTE_CAPABILITIES[$routeName] ?? null;

        if ($relevant === null) {
            // Unknown route — return the full set rather than risk dropping a
            // capability the controller needs to advertise.
            return $negotiated;
        }

        $filtered = [];
        foreach ($relevant as $capName) {
            if (isset($negotiated[$capName])) {
                $filtered[$capName] = $negotiated[$capName];
            }
            // Extensions that extend this capability should ride along too.
            foreach ($negotiated as $advertisedName => $entries) {
                foreach ($entries as $entry) {
                    $extends = $entry['extends'] ?? null;
                    if ((\is_string($extends) && $extends === $capName)
                        || (\is_array($extends) && \in_array($capName, $extends, true))) {
                        $filtered[$advertisedName] = $entries;
                    }
                }
            }
        }

        return $filtered === [] ? $negotiated : $filtered;
    }
}
