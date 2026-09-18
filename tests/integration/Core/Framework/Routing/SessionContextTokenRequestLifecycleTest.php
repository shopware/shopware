<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;

/**
 * The session context source driven through the real HTTP kernel, where the listener order decides
 * whether it can work at all: the sales channel is only known from kernel.controller (-2), while the
 * token has to be in place before context resolution (-10).
 *
 * @internal
 */
#[Package('framework')]
class SessionContextTokenRequestLifecycleTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private string $sessionName;

    protected function setUp(): void
    {
        /** @var array<string, mixed> $sessionOptions */
        $sessionOptions = static::getContainer()->getParameter('session.storage.options');
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    public function testAStoreApiRequestContinuesTheTokenTheStorefrontPageEstablished(): void
    {
        $browser = $this->createSessionSourcedBrowser();
        $storefrontToken = $this->browseTheStorefront($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource());
        $response = $browser->getResponse();

        static::assertSame($storefrontToken, $this->tokenOf($browser));
        static::assertFalse(
            $response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'a session sourced client manages no token, so it is not handed one back'
        );
        static::assertTrue(
            $response->headers->hasCacheControlDirective('no-store'),
            (string) $response->headers->get('cache-control')
        );
    }

    public function testTheSameSessionKeepsAnsweringWithTheSameToken(): void
    {
        $browser = $this->createSessionSourcedBrowser();
        $this->browseTheStorefront($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource());
        $first = $this->tokenOf($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource());

        static::assertSame($first, $this->tokenOf($browser), 'the token comes from the session, not minted per request');
    }

    public function testWithoutTheOptInTheSameBrowserGetsItsOwnContext(): void
    {
        $browser = $this->createSessionSourcedBrowser();
        $this->browseTheStorefront($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource());
        $sessionToken = $this->tokenOf($browser);

        // same cookie jar, no declared source: the session cookie alone changes nothing
        $browser->request('GET', '/store-api/context');

        static::assertNotSame($sessionToken, $this->tokenOf($browser));
    }

    public function testWithoutAStorefrontSessionTheRequestFailsInsteadOfMintingOne(): void
    {
        $browser = $this->createSessionSourcedBrowser();

        // the storefront was never visited, so the browser holds no session cookie
        $browser->request('GET', '/store-api/context', server: $this->sessionSource());
        $response = $browser->getResponse();

        static::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(RoutingException::SESSION_CONTEXT_NOT_RESOLVABLE, $this->errorCodeOf($browser));
    }

    /**
     * Under customer binding the token lives under a sales channel suffixed key, so the borrower can
     * only find it once SalesChannelAuthenticationListener (-2) has established the channel. This is
     * what pins resolveFromSession() behind it.
     */
    public function testUnderCustomerBindingTheChannelMustAlreadyBeKnown(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);

        $browser = $this->createSessionSourcedBrowser();
        $storefrontToken = $this->browseTheStorefront($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource());

        static::assertSame($storefrontToken, $this->tokenOf($browser));
    }

    public function testDeclaringBothSourcesFails(): void
    {
        $browser = $this->createSessionSourcedBrowser();
        $this->browseTheStorefront($browser);

        $browser->request('GET', '/store-api/context', server: $this->sessionSource() + [
            self::header(PlatformRequest::HEADER_CONTEXT_TOKEN) => 'a-token-of-its-own',
        ]);

        static::assertSame(400, $browser->getResponse()->getStatusCode());
        static::assertSame(RoutingException::SESSION_CONTEXT_NOT_RESOLVABLE, $this->errorCodeOf($browser));
    }

    /**
     * The shared helper presets a random `sw-context-token` header, which would conflict with the
     * declared session source on every request.
     */
    private function createSessionSourcedBrowser(): KernelBrowser
    {
        $browser = $this->createCustomSalesChannelBrowser();

        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            self::header(PlatformRequest::HEADER_ACCESS_KEY) => $browser->getServerParameter(self::header(PlatformRequest::HEADER_ACCESS_KEY)),
        ]);

        return $browser;
    }

    /**
     * Visits a storefront page so it starts the session and mints its context token.
     *
     * TestSessionStorage keeps one session for the whole process and reports it as empty while its
     * bags are being registered, which is when Symfony decides whether to send the cookie. The
     * browser is therefore handed the cookie a real storage would have set.
     *
     * @return string the context token the storefront established
     */
    private function browseTheStorefront(KernelBrowser $browser): string
    {
        $browser->request('GET', '/');
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $session = $this->getSession();
        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token, 'the storefront page has to establish the session and its token');

        $browser->getCookieJar()->set(new Cookie($this->sessionName, $session->getId()));

        return $token;
    }

    /**
     * @return array<string, string>
     */
    private function sessionSource(): array
    {
        return [
            self::header(PlatformRequest::HEADER_CONTEXT_SOURCE) => SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION,
        ];
    }

    private static function header(string $name): string
    {
        return 'HTTP_' . str_replace('-', '_', mb_strtoupper($name));
    }

    private function tokenOf(KernelBrowser $browser): string
    {
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $token = $this->contentOf($browser)['token'] ?? null;
        static::assertIsString($token);

        return $token;
    }

    private function errorCodeOf(KernelBrowser $browser): string
    {
        $code = $this->contentOf($browser)['errors'][0]['code'] ?? null;
        static::assertIsString($code);

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentOf(KernelBrowser $browser): array
    {
        $content = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($content);

        return $content;
    }
}
