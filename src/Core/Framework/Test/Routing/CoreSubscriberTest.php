<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;

class CoreSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;

    public function testDefaultHeadersHttp(): void
    {
        $browser = $this->getBrowser();
        $v = $this->getContainer()->getParameter('kernel.supported_api_versions')[0];

        $browser->request('GET', '/api/v' . $v . '/category');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has('X-Frame-Options'));
        static::assertTrue($response->headers->has('X-Content-Type-Options'));
        static::assertTrue($response->headers->has('Content-Security-Policy'));

        static::assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function testDefaultHeadersHttps(): void
    {
        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTPS', true);
        $v = $this->getContainer()->getParameter('kernel.supported_api_versions')[0];

        $browser->request('GET', '/api/v' . $v . '/category');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has('X-Frame-Options'));
        static::assertTrue($response->headers->has('X-Content-Type-Options'));
        static::assertTrue($response->headers->has('Content-Security-Policy'));

        static::assertTrue($response->headers->has('Strict-Transport-Security'));
    }

    public function testStorefrontNoCsp(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', $_SERVER['APP_URL']);
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has('X-Frame-Options'));
        static::assertTrue($response->headers->has('X-Content-Type-Options'));
        static::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testAdminHasCsp(): void
    {
        $browser = $this->getBrowser();
        $browser->request('GET', $_SERVER['APP_URL'] . '/admin');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has('X-Frame-Options'));
        static::assertTrue($response->headers->has('X-Content-Type-Options'));
        static::assertTrue($response->headers->has('Content-Security-Policy'));

        $request = $browser->getRequest();
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CSP_NONCE));
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);

        static::assertRegExp(
            '/.*script-src[^;]+nonce-' . preg_quote($nonce, '/') . '.*/',
            $response->headers->get('Content-Security-Policy'),
            'CSP should contain the nonce'
        );
        static::assertStringNotContainsString("\n", $response->headers->get('Content-Security-Policy'));
        static::assertStringNotContainsString("\r", $response->headers->get('Content-Security-Policy'));
    }

    public function testSwaggerHasCsp(): void
    {
        $browser = $this->getBrowser();
        $v = $this->getContainer()->getParameter('kernel.supported_api_versions')[0];

        $browser->request('GET', '/api/v' . $v . '/_info/swagger.html');
        $response = $browser->getResponse();

        static::assertTrue($response->headers->has('X-Frame-Options'));
        static::assertTrue($response->headers->has('X-Content-Type-Options'));
        static::assertTrue($response->headers->has('Content-Security-Policy'));

        $request = $browser->getRequest();
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_CSP_NONCE));
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);

        static::assertRegExp(
            '/.*script-src[^;]+nonce-' . preg_quote($nonce, '/') . '.*/',
            $response->headers->get('Content-Security-Policy'),
            'CSP should contain the nonce'
        );
    }
}
