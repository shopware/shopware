<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 * @group skip-paratest
 */
class CsrfRouteListenerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testPostRequestWithoutCsrfTokenShouldFail(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $client = $this->createSalesChannelBrowser($this->getKernel(), true);
        $client->request('POST', 'http://localhost/widgets/account/newsletter');
        $statusCode = $client->getResponse()->getStatusCode();
        static::assertSame(Response::HTTP_FORBIDDEN, $statusCode, (string) $client->getResponse()->getContent());
        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);

        static::assertSame(['danger' => ['Your session has expired. Please return to the last page and try again.']], $session->getFlashBag()->all());
    }

    public function testPostRequestWithValidCsrfToken(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $client = $this->createSalesChannelBrowser($this->getKernel(), true);
        $client->request('POST', 'http://localhost/widgets/account/newsletter', $this->tokenize('frontend.account.newsletter', []));
        $statusCode = $client->getResponse()->getStatusCode();

        static::assertSame(Response::HTTP_FOUND, $statusCode, (string) $client->getResponse()->getContent());
        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        static::assertSame([], $session->getFlashBag()->all());
    }
}
