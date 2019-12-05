<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class CsrfRouteListenerTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use BasicTestDataBehaviour;

    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var KernelBrowser
     */
    private $client;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        $this->kernel = KernelLifecycleManager::bootKernel();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->beginTransaction();

        // Kernel has to be rebooted every time, because the route listener keeps track, if it has already checked
        // the csrf token for a request and then stops execution if the check has already been done.
        $this->client = $this->createSalesChannelBrowser($this->kernel, true);
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
    }

    public function testPostRequestWithoutCsrfTokenShouldFail(): void
    {
        $this->client->request('POST', 'http://localhost/widgets/account/newsletter');
        $statusCode = $this->client->getResponse()->getStatusCode();
        static::assertSame(Response::HTTP_FORBIDDEN, $statusCode);
    }

    public function testPostRequestWithValidCsrfToken(): void
    {
        $token = $this->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('frontend.account.newsletter')
            ->getValue();

        $this->client->request('POST', 'http://localhost/widgets/account/newsletter', ['_csrf_token' => $token]);
        $statusCode = $this->client->getResponse()->getStatusCode();

        static::assertSame(Response::HTTP_FOUND, $statusCode);
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }

    protected function getKernel(): KernelInterface
    {
        return $this->kernel;
    }
}
