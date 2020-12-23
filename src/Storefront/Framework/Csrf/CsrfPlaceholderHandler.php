<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Csrf;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfPlaceholderHandler
{
    public const CSRF_PLACEHOLDER = '1b4dfebfc2584cf58b63c72c20d521d0';

    /**
     * @var CsrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var bool
     */
    private $csrfEnabled;

    /**
     * @var string
     */
    private $csrfMode;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager, bool $csrfEnabled, string $csrfMode)
    {
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfEnabled = $csrfEnabled;
        $this->csrfMode = $csrfMode;
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

        return $response;
    }

    private function getToken(string $intent): string
    {
        return $this->csrfTokenManager->getToken($intent)->getValue();
    }
}
