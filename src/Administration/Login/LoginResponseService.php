<?php declare(strict_types=1);

namespace Shopware\Administration\Login;

use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class LoginResponseService
{
    private const ADMIN_ROUTE_NAME = 'administration.index';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(ResponseInterface $response): RedirectResponse
    {
        $redirectUrl = $this->urlGenerator->generate(self::ADMIN_ROUTE_NAME);
        $redirectResponse = new RedirectResponse($redirectUrl);
        $redirectResponse->headers->setCookie($this->createCookie($response, $redirectUrl));

        return $redirectResponse;
    }

    public function createErrorResponse(string $email): RedirectResponse
    {
        $redirectUrl = $this->urlGenerator->generate(self::ADMIN_ROUTE_NAME);

        $redirectResponse = new RedirectResponse($redirectUrl . '/#/sso/error');

        $cookie = new Cookie(
            'user',
            $email,
            $this->createTimeStamp(60),
            $redirectUrl,
            httpOnly: false,
            sameSite: Cookie::SAMESITE_STRICT
        );

        $redirectResponse->headers->setCookie($cookie);

        return $redirectResponse;
    }

    private function createCookie(ResponseInterface $response, string $path): Cookie
    {
        $cookieData = $this->createCookieData($response);

        return new Cookie(
            'bearerAuth',
            \json_encode($cookieData, \JSON_THROW_ON_ERROR),
            (int) $cookieData['expiry'],
            $path,
            httpOnly: false,
            sameSite: Cookie::SAMESITE_STRICT
        );
    }

    /**
     * @return array{access: string, refresh: string, expiry: int}
     */
    private function createCookieData(ResponseInterface $response): array
    {
        $data = json_decode($response->getBody()->__toString(), true);

        return [
            'access' => $data['access_token'],
            'refresh' => $data['refresh_token'],
            'expiry' => $this->createTimeStamp($data['expires_in']),
        ];
    }

    private function createTimeStamp(int $expiresIn): int
    {
        return strtotime('+' . $expiresIn . ' seconds') * 1000;
    }
}
