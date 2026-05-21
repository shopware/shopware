<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Embedded;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Embedded Protocol (EP) entry points per `embedded-protocol.md` and the
 * capability-specific bindings (`embedded-cart.md`, `embedded-checkout.md`).
 *
 * EP is *iframe-bound*: the host opens our URL in an iframe/webview, our
 * page sends JSON-RPC 2.0 messages over `window.postMessage` describing the
 * embedded state (`ec.*` for checkout, `ep.cart.*` for cart), and the host
 * replies with delegation results (`ec.delegate_response`, …).
 *
 * Server-side responsibility:
 *   - Render the iframe HTML scaffolding with the per-capability bridge JS
 *     embedded (the bridge handles postMessage routing and delegates REST
 *     calls back to `/ucp/v1/...`).
 *   - Issue a short-lived session token bound to the cart token + origin
 *     allowlist for use over the postMessage channel.
 *   - Set X-Frame-Options / CSP frame-ancestors restrictively so only
 *     allowlisted hosts can embed us.
 *
 * The runtime JSON-RPC routing happens browser-side; this controller only
 * bootstraps the embedded context.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class EmbeddedController
{
    public function __construct(
        private readonly EmbeddedSessionFactory $sessionFactory,
    ) {
    }

    #[Route(
        path: '/ucp/embedded/checkout/{cartId}',
        name: 'ucp.embedded.checkout.start',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['GET']
    )]
    public function checkout(Request $request, string $cartId): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $context = $this->resolveContext($request);
        $hostOrigin = $this->resolveHostOrigin($request);

        $session = $this->sessionFactory->issue(
            cartId: $cartId,
            salesChannelId: $context->config->getSalesChannelId(),
            hostOrigin: $hostOrigin,
            kind: 'checkout',
        );

        return $this->renderEmbeddedPage(
            title: 'Checkout',
            session: $session,
            kind: 'checkout',
            hostOrigin: $hostOrigin,
        );
    }

    #[Route(
        path: '/ucp/embedded/cart/{cartId}',
        name: 'ucp.embedded.cart.start',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['GET']
    )]
    public function cart(Request $request, string $cartId): Response
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $context = $this->resolveContext($request);
        $hostOrigin = $this->resolveHostOrigin($request);

        $session = $this->sessionFactory->issue(
            cartId: $cartId,
            salesChannelId: $context->config->getSalesChannelId(),
            hostOrigin: $hostOrigin,
            kind: 'cart',
        );

        return $this->renderEmbeddedPage(
            title: 'Cart',
            session: $session,
            kind: 'cart',
            hostOrigin: $hostOrigin,
        );
    }

    private function resolveContext(Request $request): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }

        return $context;
    }

    /**
     * Resolve and validate the host origin per EP §"Channel Setup". The host
     * MUST identify itself via `?origin=<https-url>` so we can:
     *   - target `postMessage` to an exact origin (never `*`)
     *   - tighten CSP `frame-ancestors` to that origin
     *
     * We do NOT fall back to `Referer` or `*` — both can be forged or omitted
     * and would silently weaken the trust model into a clickjacking surface.
     * If `origin` is missing/invalid, the embedded session is refused.
     */
    private function resolveHostOrigin(Request $request): string
    {
        $origin = $request->query->get('origin');
        if (!\is_string($origin) || $origin === '') {
            throw UcpException::invalidProfileUrl('(missing required `origin` query parameter)');
        }
        if (!filter_var($origin, \FILTER_VALIDATE_URL)) {
            throw UcpException::invalidProfileUrl('(`origin` must be a valid absolute URL)');
        }
        $parts = parse_url($origin);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw UcpException::invalidProfileUrl('(malformed `origin`)');
        }
        if (strtolower($parts['scheme']) !== 'https') {
            // Allow http://localhost / http://host.docker.internal in non-prod for testing.
            $isLocalDev = \in_array(strtolower($parts['host']), ['localhost', 'host.docker.internal', '127.0.0.1', '::1'], true);
            if (!$isLocalDev) {
                throw UcpException::invalidProfileUrl('(`origin` must use HTTPS in production)');
            }
        }

        // Canonical origin form: scheme://host[:port], no path/query/fragment.
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . $port;
    }

    private function renderEmbeddedPage(
        string $title,
        EmbeddedSession $session,
        string $kind,
        string $hostOrigin
    ): Response {
        $sessionJson = json_encode([
            'session_id' => $session->id,
            'session_token' => $session->token,
            'cart_id' => $session->cartId,
            'kind' => $kind,
            'host_origin' => $hostOrigin,
            'expires_at' => $session->expiresAt->format(\DateTimeInterface::ATOM),
            'rest_endpoint' => '/ucp/v1',
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        $bridge = $this->embeddedBridgeScript($kind);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>
  :root { font-family: system-ui, sans-serif; color-scheme: light dark; }
  body { margin: 0; padding: 16px; }
  .ucp-embedded-status { font-size: 14px; opacity: 0.7; }
</style>
</head>
<body>
<div id="ucp-embedded-root">
  <p class="ucp-embedded-status">Loading {$kind}…</p>
</div>
<script>
  window.UCP_EMBEDDED_SESSION = {$sessionJson};
</script>
<script>
{$bridge}
</script>
</body>
</html>
HTML;

        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        // EP §"Security": deny embedding except by the negotiated host origin.
        // X-Frame-Options ALLOW-FROM is obsolete (ignored by modern browsers);
        // CSP frame-ancestors is the real control we rely on.
        $response->headers->set('Content-Security-Policy', 'frame-ancestors \'self\' ' . $hostOrigin);
        $response->headers->set('Cache-Control', 'no-store, private');
        // Strict referrer policy so the iframe URL (with cart token) does not
        // leak via Referer when the host page later navigates elsewhere.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function embeddedBridgeScript(string $kind): string
    {
        $prefix = $kind === 'cart' ? 'ep.cart' : 'ec';

        return <<<JS
(function () {
  const session = window.UCP_EMBEDDED_SESSION;
  const hostOrigin = session.host_origin;
  const restEndpoint = session.rest_endpoint;
  const prefix = "{$prefix}";

  function emit(method, params, id) {
    const msg = { jsonrpc: '2.0', method, params: params || {} };
    if (id !== undefined) msg.id = id;
    // Always target the negotiated host origin exactly — never use '*'
    // (would leak the message to whatever site is currently the parent).
    window.parent.postMessage(msg, hostOrigin);
  }

  function respond(id, result, error) {
    const msg = { jsonrpc: '2.0', id };
    if (error) msg.error = error;
    else msg.result = result;
    window.parent.postMessage(msg, hostOrigin);
  }

  async function rest(method, path, body) {
    const init = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'sw-context-token': session.cart_id,
        'X-UCP-Embedded-Session': session.session_token,
      },
    };
    if (body !== undefined) init.body = JSON.stringify(body);
    const r = await fetch(restEndpoint + path, init);
    return { status: r.status, body: await r.json().catch(() => null) };
  }

  async function loadAndPublishState() {
    const { status, body } = await rest('GET', '/carts/' + encodeURIComponent(session.cart_id));
    if (status === 200) {
      emit(prefix + '.state_changed', { state: body });
    } else {
      emit(prefix + '.error', { code: 'load_failed', http_status: status, body });
    }
  }

  window.addEventListener('message', async (e) => {
    // Strict origin check — drop messages from any other window.
    if (e.origin !== hostOrigin) return;
    const m = e.data;
    if (!m || m.jsonrpc !== '2.0' || !m.method) return;

    try {
      switch (m.method) {
        case prefix + '.ready':
          await loadAndPublishState();
          respond(m.id, { ok: true });
          break;
        case prefix + '.refresh':
          await loadAndPublishState();
          respond(m.id, { ok: true });
          break;
        case prefix + '.update': {
          const { status, body } = await rest('PUT', '/carts/' + encodeURIComponent(session.cart_id), m.params || {});
          respond(m.id, { http_status: status, state: body });
          if (status < 300) emit(prefix + '.state_changed', { state: body });
          break;
        }
        case prefix + '.cancel': {
          await rest('POST', '/carts/' + encodeURIComponent(session.cart_id) + '/cancel');
          respond(m.id, { ok: true });
          break;
        }
        case prefix + '.complete': {
          const { status, body } = await rest('POST', '/checkout-sessions/' + encodeURIComponent(session.cart_id) + '/complete', m.params || {});
          respond(m.id, { http_status: status, state: body });
          if (body && body.status === 'completed') emit(prefix + '.completed', { order_id: body.order_id, state: body });
          break;
        }
        default:
          respond(m.id, undefined, { code: -32601, message: 'method not found: ' + m.method });
      }
    } catch (err) {
      respond(m.id, undefined, { code: -32603, message: err && err.message ? err.message : 'internal' });
    }
  });

  // Announce ourselves to the host.
  emit(prefix + '.ready_handshake', { session_id: session.session_id, expires_at: session.expires_at });
})();
JS;
    }
}
