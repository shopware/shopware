<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\StoreApiProxyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class StoreApiProxyControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    private Request $request;

    private EntityRepositoryInterface $customerRepository;

    private TestDataCollection $ids;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->ids = new TestDataCollection();
        $this->salesChannelContext = $this->createSalesChannelContext();
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

    public function testErrorOccurs(): void
    {
        $response = $this->request('POST', '/store-api/salutation', [
            'limit' => 'ABC',
        ]);

        static::assertSame(400, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);

        static::assertNotEmpty($json);
        static::assertCount(1, $json['errors']);
        static::assertSame('FRAMEWORK__INVALID_LIMIT_QUERY', $json['errors'][0]['code']);
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

    public function testDifferentTranslationReadingWorks(): void
    {
        $ids = new TestDataCollection();

        $secondLanguage = Uuid::randomHex();
        $this->createLanguage($secondLanguage);

        $this->getContainer()->get('sales_channel.repository')->update([
            [
                'id' => $this->salesChannelContext->getSalesChannelId(),
                'languageId' => $secondLanguage,
                'languages' => [
                    [
                        'id' => $secondLanguage,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->salesChannelContext->getContext()->assign(['languageIdChain' => [$secondLanguage]]);

        $productRepository = $this->getContainer()->get('product.repository');

        $productRepository->create([
            (new ProductBuilder($ids, 'p1'))
                ->visibility($this->salesChannelContext->getSalesChannelId())
                ->active(true)
                ->name('Default')
                ->price(50, 50)
                ->translation($secondLanguage, 'name', 'Second')
                ->build(),
        ], Context::createDefaultContext());

        $response = $this->request('POST', '/store-api/product/' . $ids->get('p1'), [
            'limit' => 1,
        ]);

        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true)['product']['translated'];

        static::assertSame('Second', $json['name']);
    }

    public function testHeaderLanguageIsConsidered(): void
    {
        $secondLanguage = Uuid::randomHex();
        $this->createLanguage($secondLanguage);

        $this->getContainer()->get('sales_channel.repository')->update([
            [
                'id' => $this->salesChannelContext->getSalesChannelId(),
                'languages' => [
                    [
                        'id' => $secondLanguage,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->salesChannelContext->getContext()->assign(['languageIdChain' => [$secondLanguage]]);

        $response = $this->request('GET', '/store-api/context', [], [PlatformRequest::HEADER_LANGUAGE_ID => $secondLanguage]);

        static::assertSame(200, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        static::assertSame($secondLanguage, $json['context']['languageIdChain'][0]);

        if (!Feature::isActive('FEATURE_NEXT_17276')) {
            static::assertSame($secondLanguage, $json['salesChannel']['languageId']);
        }
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

    private function request(string $method, string $url, array $body = [], array $headers = []): Response
    {
        $urlComponents = parse_url($url);
        $query = [];

        if (isset($urlComponents['query'])) {
            parse_str($urlComponents['query'], $query);
        }

        if (\strlen($url)) {
            $query['path'] = $url;
        }

        $this->request = new Request($query, $body);
        $this->request->setMethod($method);
        $this->request->server->set('REQUEST_URI', \is_array($urlComponents) ? $urlComponents['path'] : $url);
        $this->request->setSession(new Session(new MockArraySessionStorage()));

        $this->request->headers->replace($headers);

        return $this->getContainer()->get(StoreApiProxyController::class)->proxy($this->request, $this->salesChannelContext);
    }

    private function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'translationCode' => [
                        'id' => Uuid::randomHex(),
                        'code' => 'bla',
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                    'salesChannelDefaultAssignments' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
