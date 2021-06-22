<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @group skip-paratest
 */
class CsrfRouteListenerTest extends TestCase
{
    use SalesChannelApiTestBehaviour;
    use BasicTestDataBehaviour;
    use StorefrontControllerTestBehaviour;

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
        $this->getFlashBag()->clear();
    }

    public function tearDown(): void
    {
        $this->getFlashBag()->clear();
        $this->connection->rollBack();
    }

    public function testPostRequestWithoutCsrfTokenShouldFail(): void
    {
        $this->client->request('POST', 'http://localhost/widgets/account/newsletter');
        $statusCode = $this->client->getResponse()->getStatusCode();
        static::assertSame(Response::HTTP_FORBIDDEN, $statusCode, $this->client->getResponse()->getContent());
        static::assertSame(['danger' => ['Your session has expired. Please return to the last page and try again.']], $this->getFlashBag()->all());
    }

    public function testPostRequestWithValidCsrfToken(): void
    {
        $this->client->request('POST', 'http://localhost/widgets/account/newsletter', $this->tokenize('frontend.account.newsletter', []));
        $statusCode = $this->client->getResponse()->getStatusCode();

        static::assertSame(Response::HTTP_FOUND, $statusCode);
        static::assertSame([], $this->getFlashBag()->all());
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }

    protected function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    private function getFlashBag(): FlashBagInterface
    {
        return $this->getContainer()->get('session')->getFlashBag();
    }
}
