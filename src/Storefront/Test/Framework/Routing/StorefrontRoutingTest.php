<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Routing\RequestTransformer as CoreRequestTransformer;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;

class StorefrontRoutingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RequestContext
     */
    private $oldContext;

    /**
     * @var SeoUrlPlaceholderHandlerInterface
     */
    private $seoUrlReplacer;

    public function setUp(): void
    {
        $this->requestTransformer = new RequestTransformer(
            new CoreRequestTransformer(),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(SeoResolver::class)
        );

        $this->seoUrlReplacer = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $this->requestStack = $this->getContainer()->get('request_stack');
        while ($this->requestStack->pop()) {
        }
        $this->router = $this->getContainer()->get('router');
        $this->oldContext = $this->router->getContext();
    }

    public function tearDown(): void
    {
        while ($this->requestStack->pop()) {
        }
        $this->router->setContext($this->oldContext);
    }

    /**
     * @dataProvider getRequestTestCaseProvider
     */
    public function testInvariants(RequestTestCase $case): void
    {
        $salesChannelContext = $this->registerDomain($case);

        $request = $case->createRequest();
        $transformedRequest = $this->requestTransformer->transform($request);

        $this->requestStack->push($transformedRequest);

        $context = $this->getContext($transformedRequest);
        $this->router->setContext($context);

        $absolutePath = $this->router->generate($case->route, [], Router::ABSOLUTE_PATH);
        $absoluteUrl = $this->router->generate($case->route, [], Router::ABSOLUTE_URL);
        $networkPath = $this->router->generate($case->route, [], Router::NETWORK_PATH);
        $pathInfo = $this->router->generate($case->route, [], Router::PATH_INFO);

        static::assertSame($case->getAbsolutePath(), $absolutePath, var_export($case, true));
        static::assertSame($case->getAbsoluteUrl(), $absoluteUrl, var_export($case, true));
        static::assertSame($case->getNetworkPath(), $networkPath, var_export($case, true));
        static::assertSame($case->getPathInfo(), $pathInfo, var_export($case, true));

        $matches = $this->router->matchRequest($transformedRequest);
        static::assertEquals($case->route, $matches['_route']);

        $matches = $this->router->match($transformedRequest->getPathInfo());
        static::assertEquals($case->route, $matches['_route']);

        // test seo url generation
        $host = $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL)
            . $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL);

        $absoluteSeoUrl = $this->seoUrlReplacer->replace(
            $this->seoUrlReplacer->generate(
                $case->route,
                []
            ),
            $host,
            $salesChannelContext
        );

        static::assertSame($case->getAbsoluteUrl(), $absoluteSeoUrl);
    }

    public function getRequestTestCaseProvider(): array
    {
        $config = [
            'https' => [false, true],
            'host' => ['router.test', 'router.test:8000'],
            'subDir' => ['', '/public', '/sw/public'],
            'salesChannel' => ['', '/de', '/de/premium', '/public'],
        ];
        $cases = $this->generateCases(array_keys($config), $config);

        return array_map(function ($params) {
            return [$this->createCase($params['https'], $params['host'], $params['subDir'], $params['salesChannel'])];
        }, $cases);
    }

    private function getContext(Request $request): RequestContext
    {
        return new RequestContext(
            $request->getBaseUrl(),
            $request->getMethod(),
            $request->getHost(),
            $request->getScheme(),
            (!$request->isSecure() && $request->getPort() !== 80) ? $request->getPort() : 80,
            ($request->isSecure() && $request->getPort() !== 443) ? $request->getPort() : 443,
            $request->getPathInfo(),
            ''
        );
    }

    private function createCase(bool $https, string $host, string $subDir, string $salesChannel): RequestTestCase
    {
        return new RequestTestCase(
            'POST',
            'frontend.account.register.save',
            '/app' . $subDir . '/index.php',
            $subDir . '/index.php',
            $host,
            $subDir . $salesChannel . '/account/register',
            '/account/register',
            $salesChannel,
            $https
        );
    }

    private function generateCases(array $keys, array $config): array
    {
        if (empty($keys)) {
            return [];
        }

        $results = [];
        $key = array_pop($keys);
        foreach ($config[$key] as $value) {
            $childResults = $this->generateCases($keys, $config);
            $base = [$key => $value];
            foreach ($childResults as $childResult) {
                $base = array_merge($base, $childResult);
                $results[] = $base;
            }
            if (empty($childResults)) {
                $results[] = $base;
            }
        }

        return $results;
    }

    private function registerDomain(RequestTestCase $case): SalesChannelContext
    {
        $request = $case->createRequest();
        $salesChannel = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'url' => ($case->https ? 'https://' : 'http://') . $case->host . $request->getBaseUrl() . $case->salesChannelPrefix,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ];

        return $this->createSalesChannels([$salesChannel]);
    }

    private function createSalesChannels(array $salesChannels): SalesChannelContext
    {
        $salesChannels = array_map(function ($salesChannelData) {
            $defaults = [
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'currencyId' => Defaults::CURRENCY,
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
                'countryId' => $this->getValidCountryId(),
                'countryVersionId' => Defaults::LIVE_VERSION,
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
                'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
                'countries' => [['id' => $this->getValidCountryId()]],
                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            ];

            return array_merge_recursive($defaults, $salesChannelData);
        }, $salesChannels);

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $event = $salesChannelRepository->create($salesChannels, Context::createDefaultContext());

        $id = $event->getEventByEntityName($salesChannelRepository->getDefinition()->getEntityName())->getIds()[0];

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), $id);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
