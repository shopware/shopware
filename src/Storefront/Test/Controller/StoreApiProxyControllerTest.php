<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StoreApiProxyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class StoreApiProxyControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->ids = new TestDataCollection();
    }

    public function testSalutation(): void
    {
        $response = $this->request('GET', '/store-api/salutation');

        static::assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertSame('salutation', $json['elements'][0]['apiAlias']);
    }

    public function testSalutationWithPreviousRequestInStack(): void
    {
        $this->getContainer()->get('request_stack')->push(new Request());

        $response = $this->request('GET', '/store-api/salutation');

        static::assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertSame('salutation', $json['elements'][0]['apiAlias']);
    }

    public function testSalutationLimitWorksInQuery(): void
    {
        $response = $this->request('GET', '/store-api/salutation?limit=1');

        static::assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertCount(1, $json['elements']);
    }

    public function testSalutationLimitWorksInBody(): void
    {
        $response = $this->request('POST', '/store-api/salutation', [
            'limit' => 1,
        ]);

        static::assertSame(200, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertCount(1, $json['elements']);
    }

    public function test404WillBeForwarded(): void
    {
        $response = $this->request('GET', '/store-api/');
        static::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidUrl(): void
    {
        static::expectException(InvalidRequestParameterException::class);
        $response = $this->request('GET', ':');
        static::assertSame(500, $response->getStatusCode());
    }

    public function testStorefrontUrl(): void
    {
        static::expectException(InvalidRequestParameterException::class);
        $response = $this->request('GET', '/');
        static::assertSame(500, $response->getStatusCode());
    }

    public function testMissingUrl(): void
    {
        static::expectException(MissingRequestParameterException::class);
        $response = $this->request('GET', '');
        static::assertSame(500, $response->getStatusCode());
    }

    public function testCustomerLoginChangesTokenInSession(): void
    {
        $customerId = $this->createCustomer('shopware', 'store-api-proxy@localhost.de');

        $this->request('POST', '/store-api/account/login', [
            'username' => 'store-api-proxy@localhost.de',
            'password' => 'shopware',
        ]);

        static::assertTrue($this->request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $token = $this->request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $tokenData = $this->getContainer()->get(SalesChannelContextPersister::class)
            ->load($token, $this->salesChannelContext->getSalesChannelId(), $customerId);

        static::assertArrayHasKey('customerId', $tokenData);
        static::assertSame($customerId, $tokenData['customerId']);
    }

    private function request(string $method, string $url, array $body = []): Response
    {
        $urlComponents = parse_url($url);
        $query = [];

        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $query);
        }

        if (\strlen($url)) {
            $query['path'] = $url;
        }

        $this->salesChannelContext = $this->createSalesChannelContext();

        $this->request = new Request($query, $body);
        $this->request->setMethod($method);
        $this->request->server->set('REQUEST_URI', \is_array($urlComponents) ? $urlComponents['path'] : $url);
        $this->request->setSession(new Session(new MockArraySessionStorage()));

        return $this->getContainer()->get(StoreApiProxyController::class)->proxy($this->request, $this->salesChannelContext);
    }
}
