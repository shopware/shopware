<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfPlaceholderHandler
{
    public const CSRF_PLACEHOLDER = '1b4dfebfc2584cf58b63c72c20d521d0';

    private CsrfTokenManagerInterface $csrfTokenManager;

    private bool $csrfEnabled;

    private string $csrfMode;

    private RequestStack $requestStack;

    private SessionProvider $sessionProvider;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled, string $csrfMode, RequestStack $requestStack, SessionProvider $sessionProvider)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
        $this->requestStack = $requestStack;
        $this->sessionProvider = $sessionProvider;
    }

    public function replaceCsrfToken(Response $response, Request $request): Response
    {
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        if (!$this->csrfEnabled || $this->csrfMode !== CsrfModes::MODE_TWIG) {
            return $response;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false) {
            return $response;
        }

        // Early return if the placeholder is not present in body to save cpu cycles with the regex
        if (!\str_contains($content, self::CSRF_PLACEHOLDER)) {
            return $response;
        }

        // Get session from session provider if not provided in session. This happens when the page is fully cached
        $session = $request->hasSession() ? $request->getSession() : $this->sessionProvider->getSession();
        $request->setSession($session);

        if ($session !== null) {
            // StorefrontSubscriber did not run and set the session name. This can happen when the page is fully cached in the http cache
            if (!$session->isStarted()) {
                $session->setName('session-');
            }

            // The SessionTokenStorage gets the session from the RequestStack. This is at this moment empty as the Symfony request cycle did run already
            $this->requestStack->push($request);
        }

        // https://regex101.com/r/fefx3V/1
        $content = preg_replace_callback(
            '/' . self::CSRF_PLACEHOLDER . '(?<intent>[^#]*)#/',
            function ($matches) use ($response, $request) {
                $token = $this->getToken($matches['intent']);

                $cookie = Cookie::create('csrf[' . $matches['intent'] . ']', $token);

                $cookie->setSecureDefault($request->isSecure());

                $response->headers->setCookie($cookie);

                return $token;
            },
            $content
        );

        $response->setContent($content);

        if ($session !== null) {
            // Pop out the request injected some lines above. This is important for long running applications with roadrunner or swoole
            $this->requestStack->pop();
        }

        return $response;
    }

    private function getToken(string $intent): string
    {
        return $this->csrfTokenManager->getToken($intent)->getValue();
    }
}
