<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Integration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

class CsrfRouteListenerTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;

    private $client;

    public function setUp(): void
    {
        // Kernel has to be rebooted every time, because the route listener keeps track, if it has already checked
        // the csrf token for a request and then stops execution if the check has already been done.
        $this->client = $this->createSalesChannelBrowser(KernelLifecycleManager::bootKernel(), true);
    }

    public function testPostRequestWithoutCsrfTokenShouldFail(): void
    {
        $this->client->request('POST', getenv('APP_URL') . '/newsletter');
        $statusCode = $this->client->getResponse()->getStatusCode();
        static::assertSame(Response::HTTP_FORBIDDEN, $statusCode);
    }

    public function testPostRequestWithValidCsrfToken(): void
    {
        $token = $this->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('frontend.newsletter.register.handle')
            ->getValue();

        $this->client->request('POST', getenv('APP_URL') . '/newsletter', ['_csrf_token' => $token]);
        $statusCode = $this->client->getResponse()->getStatusCode();

        static::assertSame(Response::HTTP_OK, $statusCode);
    }
}
