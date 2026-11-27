<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Storefront\Framework\Twig\TwigAppVariable;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class TwigAppVariableTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAdministrationPreservesTrustedProxyUrlWithHttpCache(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $trustedProxies = Request::getTrustedProxies();
        $trustedHeaderSet = Request::getTrustedHeaderSet();

        try {
            Request::setTrustedProxies(['10.0.0.2'], Request::HEADER_X_FORWARDED_TRAEFIK);

            $browser->request('GET', 'http://backend.internal:8000/admin', server: [
                'REMOTE_ADDR' => '10.0.0.2',
                'HTTP_X_FORWARDED_FOR' => '192.0.2.1',
                'HTTP_X_FORWARDED_HOST' => 'shop.example',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
                'HTTP_X_FORWARDED_PREFIX' => '/shop',
            ]);

            static::assertSame(200, $browser->getResponse()->getStatusCode());
            $content = $browser->getResponse()->getContent();
            static::assertIsString($content);
            static::assertStringContainsString('schemeAndHttpHost: \'https://shop.example\'', $content);
            static::assertStringContainsString('basePath: \'/shop\'', $content);
            static::assertStringContainsString('uri: \'https://shop.example/shop/admin\'', $content);
        } finally {
            // @phpstan-ignore argument.type (Restore Symfony's original header set, including its -1 default.)
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);
        }
    }

    public function testRequestCycleDoesntTouchActualRequest(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL']);
        static::assertTrue($browser->getRequest()->server->has('SERVER_PROTOCOL'));
    }

    public function testHttpsRequest(): void
    {
        $orgRequest = new Request();
        $orgRequest->server->set('HTTPS', '1');
        $orgRequest->server->set('SERVER_NAME', 'localhost');
        $orgRequest->server->set('SERVER_PORT', '443');

        static::assertTrue($orgRequest->isSecure());

        $appVariable = static::createStub(AppVariable::class);
        $appVariable->method('getRequest')->willReturn($orgRequest);

        $app = new TwigAppVariable($appVariable, static::getContainer()->getParameter('shopware.twig.app_variable.allowed_server_params'));
        $appRequest = $app->getRequest();

        static::assertNotNull($appRequest);
        static::assertTrue($appRequest->isSecure());
        static::assertSame('https://localhost', $appRequest->getSchemeAndHttpHost());
    }
}
