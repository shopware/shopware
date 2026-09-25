<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSession;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionHeader;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionService;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Registration and refresh endpoints of Device Bound Session Credentials (DBSC).
 * Both are called by the browser itself; their responses must never redirect, not even into maintenance mode.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID], PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true])]
class DeviceBoundSessionController extends StorefrontController
{
    /**
     * Paths the browser does not need to hold back while it refreshes the cookie
     */
    private const EXCLUDED_PATHS = ['/admin', '/api', '/store-api', '/bundles', '/theme', '/media', '/thumbnail'];

    public function __construct(
        private readonly bool $enabled,
        private readonly DeviceBoundSessionService $service,
    ) {
    }

    #[Route(path: '/device-bound-session/register', name: 'frontend.device_bound_session.register', methods: ['POST'])]
    public function register(Request $request, SalesChannelContext $context): Response
    {
        $proof = DeviceBoundSessionHeader::parseItem($request->headers->get(DeviceBoundSessionHeader::RESPONSE));
        if (!$this->enabled || $proof === null || $context->getCustomer() === null) {
            return $this->noStore(new Response(null, Response::HTTP_BAD_REQUEST));
        }

        $registration = $this->service->register($context->getToken(), $proof);
        if ($registration === null) {
            return $this->noStore(new Response(null, Response::HTTP_BAD_REQUEST));
        }

        return $this->credentialResponse($request, $registration['session'], $registration['cookieValue']);
    }

    #[Route(path: '/device-bound-session/refresh', name: 'frontend.device_bound_session.refresh', methods: ['POST'])]
    public function refresh(Request $request, SalesChannelContext $context): Response
    {
        if (!$this->enabled) {
            return $this->terminate();
        }

        $id = DeviceBoundSessionHeader::parseItem($request->headers->get(DeviceBoundSessionHeader::SESSION_ID));
        $session = $id !== null ? $this->service->findById($id) : null;

        // the binding is kept: it belongs to another session cookie, and removing it would unprotect that session
        if ($session === null || !hash_equals($session->contextToken, $context->getToken())) {
            return $this->terminate();
        }

        $proof = DeviceBoundSessionHeader::parseItem($request->headers->get(DeviceBoundSessionHeader::RESPONSE));
        $cookieValue = $proof !== null ? $this->service->refresh($session, $proof) : null;

        if ($cookieValue === null) {
            return $this->challenge($session);
        }

        return $this->credentialResponse($request, $session, $cookieValue);
    }

    private function credentialResponse(Request $request, DeviceBoundSession $session, string $cookieValue): Response
    {
        $cookieName = $this->service->getCookieName($session);
        $secure = $request->isSecure();

        $response = new JsonResponse([
            'session_identifier' => $session->id,
            'refresh_url' => $this->generateUrl('frontend.device_bound_session.refresh'),
            'scope' => [
                'origin' => $request->getSchemeAndHttpHost(),
                'include_site' => false,
                'scope_specification' => array_map(static fn (string $path): array => [
                    'type' => 'exclude',
                    'domain' => $request->getHost(),
                    'path' => $path,
                ], self::EXCLUDED_PATHS),
            ],
            'credentials' => [[
                'type' => 'cookie',
                'name' => $cookieName,
                // must describe the cookie below, the browser compares both
                'attributes' => 'Path=/; ' . ($secure ? 'Secure; ' : '') . 'HttpOnly; SameSite=Lax',
            ]],
            // customers return from payment providers via cross-site navigations, which must refresh as well
            'allowed_refresh_initiators' => ['*'],
        ]);

        $response->headers->setCookie(Cookie::create(
            name: $cookieName,
            value: $cookieValue,
            expire: $this->service->getCookieExpiry(),
            path: '/',
            secure: $secure,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $this->noStore($response);
    }

    private function challenge(DeviceBoundSession $session): Response
    {
        $response = new Response(null, Response::HTTP_FORBIDDEN);
        $response->headers->set(DeviceBoundSessionHeader::CHALLENGE, \sprintf(
            '%s;id=%s',
            DeviceBoundSessionHeader::serializeString($this->service->createRefreshChallenge($session)),
            DeviceBoundSessionHeader::serializeString($session->id),
        ));

        return $this->noStore($response);
    }

    private function terminate(): Response
    {
        return $this->noStore(new JsonResponse(['continue' => false]));
    }

    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
