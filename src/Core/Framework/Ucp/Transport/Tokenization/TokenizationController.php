<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Tokenization;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP Tokenization Handler endpoint per `tokenization-guide.md` §"Provider
 * Endpoint". Lets the platform delegate **raw credential collection** to
 * Shopware so the business never sees pan/cvv/etc.:
 *
 *   POST /ucp/v1/tokenize
 *     {
 *       "type": "card",
 *       "credential": {
 *         "pan": "...",
 *         "expiry_month": "12",
 *         "expiry_year": "2030",
 *         "cvc": "..."
 *       }
 *     }
 *
 * Returns:
 *
 *   {
 *     "token": "<opaque>",
 *     "expires_at": "...",
 *     "instrument_summary": { "brand": "visa", "last4": "4242" }
 *   }
 *
 * In Shopware the actual tokenisation is delegated to the configured
 * payment handler ({@see UcpPaymentHandlerRegistry}) — the handler is the
 * one with provider-side integration (Stripe / Mollie / …). Handlers that
 * don't support tokenisation simply return null and we 501 the request.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class TokenizationController
{
    public function __construct(
        private readonly UcpPaymentHandlerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/ucp/v1/tokenize',
        name: 'ucp.tokenize',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['POST']
    )]
    public function tokenize(Request $request): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'malformed_body'], 400);
        }
        $credential = $payload['credential'] ?? null;
        $binding = $payload['binding'] ?? null;
        $type = $payload['type'] ?? (\is_array($credential) ? ($credential['type'] ?? null) : null);
        $handlerId = $payload['handler_id'] ?? null;
        if (!\is_string($type) || $type === '' || !\is_array($credential)) {
            return new JsonResponse(['error' => 'missing_fields'], 400);
        }
        if ($binding !== null) {
            if (!\is_array($binding) || !\is_string($binding['checkout_id'] ?? null) || $binding['checkout_id'] === '') {
                return new JsonResponse(['error' => 'invalid_binding'], 400);
            }
        }

        $handler = \is_string($handlerId) ? $this->registry->get($handlerId) : $this->registry->findFirstSupportingTokenisation();
        if ($handler === null) {
            return new JsonResponse([
                'error' => 'not_supported',
                'message' => 'No payment handler installed that supports tokenisation. Refer to the buyer to the business storefront to enter their payment details directly.',
            ], 501);
        }

        try {
            $result = $handler->tokenize($type, $credential, $context->salesChannelContext);
        } catch (\Throwable $e) {
            $this->logger->warning('UCP tokenisation failed', ['error' => $e->getMessage(), 'handler' => $handlerId]);

            return new JsonResponse([
                'error' => 'tokenisation_failed',
                'message' => 'The selected payment handler could not tokenize this credential.',
            ], 502);
        }

        if ($result === null) {
            return new JsonResponse(['error' => 'handler_declined'], 502);
        }

        // Sanity: never return raw credential data back, only the opaque token + summary.
        return new JsonResponse([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'] ?? null,
            'instrument_summary' => $result['instrument_summary'] ?? null,
        ]);
    }
}
