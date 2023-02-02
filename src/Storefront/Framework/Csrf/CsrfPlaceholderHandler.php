<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfPlaceholderHandler
{
    public const CSRF_PLACEHOLDER = '1b4dfebfc2584cf58b63c72c20d521d0';

    private CsrfTokenManagerInterface $csrfTokenManager;

    private bool $csrfEnabled;

    private string $csrfMode;

    private RequestStack $requestStack;

    private SessionStorageFactoryInterface $sessionFactory;

    /**
     * @internal
     */
    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled, string $csrfMode, RequestStack $requestStack, SessionStorageFactoryInterface $sessionFactory)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
        $this->requestStack = $requestStack;
        $this->sessionFactory = $sessionFactory;
    }

    public function replaceCsrfToken(Response $response, Request $request): Response
    {
        if ($response instanceof StreamedResponse) {
            return $response;
        }

        if (!$this->csrfEnabled || $this->csrfMode !== CsrfModes::MODE_TWIG) {
            return $response;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK && $response->getStatusCode() !== Response::HTTP_NOT_FOUND) {
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
        $session = $request->hasSession() ? $request->getSession() : $this->createSession($request);
        $request->setSession($session);

        if ($session !== null) {
            // StorefrontSubscriber did not run and set the session name. This can happen when the page is fully cached in the http cache
            if (!$session->isStarted()) {
                $session->setName('session-');
            }

            // The SessionTokenStorage gets the session from the RequestStack. This is at this moment empty as the Symfony request cycle did run already
            $this->requestStack->push($request);
        }

        $processedIntents = [];

        // https://regex101.com/r/fefx3V/1
        $content = preg_replace_callback(
            '/' . self::CSRF_PLACEHOLDER . '(?<intent>[^#]*)#/',
            function ($matches) use ($response, $request, &$processedIntents) {
                $intent = $matches['intent'];
                $token = $processedIntents[$intent] ?? null;

                // Don't generate the token and set the cookie again
                if ($token === null) {
                    $token = $this->getToken($intent);
                    $cookie = Cookie::create('csrf[' . $intent . ']', $token);
                    $cookie->setSecureDefault($request->isSecure());
                    $response->headers->setCookie($cookie);
                    $processedIntents[$intent] = $token;
                }

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

    private function createSession(Request $request): SessionInterface
    {
        $session = new Session($this->sessionFactory->createStorage($request));
        $session->setName('session-');
        $request->setSession($session);

        return $session;
    }
}
