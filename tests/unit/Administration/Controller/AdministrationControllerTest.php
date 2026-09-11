<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\UnableToReadFile;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Events\PreResetExcludedSearchTermEvent;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdministrationController::class)]
class AdministrationControllerTest extends TestCase
{
    private Connection&Stub $connection;

    private Context $context;

    /**
     * @var EntityRepository<CurrencyCollection>&Stub
     */
    private EntityRepository&Stub $currencyRepository;

    private DefinitionInstanceRegistry&Stub $definitionRegistry;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private PrefixFilesystem&Stub $fileSystemOperator;

    private HtmlSanitizer&Stub $htmlSanitizer;

    private ParameterBagInterface&Stub $parameterBag;

    private string $shopwareCoreDir;

    private string $serviceRegistryUrl;

    /**
     * @var EntityRepository<LanguageCollection>&Stub
     */
    private EntityRepository&Stub $languageRepository;

    private string $refreshTokenTtl;

    private string $analyticsGatewayUrl;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->currencyRepository = static::createStub(EntityRepository::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->fileSystemOperator = static::createStub(PrefixFilesystem::class);
        $this->htmlSanitizer = static::createStub(HtmlSanitizer::class);
        $this->parameterBag = static::createStub(ParameterBagInterface::class);
        $this->shopwareCoreDir = __DIR__ . '/../../../../src/Core/';
        $this->serviceRegistryUrl = 'https://registry.services.shopware.io';
        $this->languageRepository = static::createStub(EntityRepository::class);
        $this->refreshTokenTtl = 'P1W';
        $this->analyticsGatewayUrl = 'https://analytics-gateway.test.com';

        $this->ids = new IdsCollection();
    }

    public function testIndexPerformsOnSearchOfCurrency(): void
    {
        $this->parameterBag->method('has')->willReturn(true);
        $this->parameterBag->method('get')->willReturn(true);

        $currencyRepository = $this->createMock(EntityRepository::class);

        $controller = $this->createAdministrationController(currencyRepository: $currencyRepository);

        $container = new Container();
        $twig = $this->createMock(Environment::class);

        $twig->expects($this->once())->method('render')
            ->willReturnArgument(0)
            ->with(
                '',
                [
                    'features' => [],
                    'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
                    'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
                    'systemCurrencyId' => Defaults::CURRENCY,
                    'systemCurrencyISOCode' => 'fakeIsoCode',
                    'liveVersionId' => Defaults::LIVE_VERSION,
                    'firstRunWizard' => false,
                    'apiVersion' => null,
                    'cspNonce' => null,
                    'adminEsEnable' => true,
                    'storefrontEsEnable' => true,
                    'serviceRegistryUrl' => $this->serviceRegistryUrl,
                    'refreshTokenTtl' => 7 * 86400 * 1000,
                    'productStreamIndexingEnabled' => true,
                    'analyticsGatewayUrl' => $this->analyticsGatewayUrl,
                ]
            );

        $container->set('twig', $twig);
        $controller->setContainer($container);

        $currencyCollection = new CurrencyCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('fakeIsoCode');
        $currencyCollection->add($currency);

        $currencyRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'currency',
                1,
                $currencyCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $response = $controller->index(new Request(), $this->context);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testIndexBlanksShopwareOperatedUrlsWhenAirGapped(): void
    {
        $this->parameterBag->method('has')->willReturn(true);
        $this->parameterBag->method('get')->willReturn(true);

        $currencyRepository = $this->createMock(EntityRepository::class);

        $controller = $this->createAdministrationController(
            currencyRepository: $currencyRepository,
            airGappedMode: new AirGappedMode(true),
        );

        $container = new Container();
        $twig = $this->createMock(Environment::class);

        $twig->expects($this->once())->method('render')
            ->willReturnArgument(0)
            ->with(
                '',
                [
                    'features' => [],
                    'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
                    'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
                    'systemCurrencyId' => Defaults::CURRENCY,
                    'systemCurrencyISOCode' => 'fakeIsoCode',
                    'liveVersionId' => Defaults::LIVE_VERSION,
                    'firstRunWizard' => false,
                    'apiVersion' => null,
                    'cspNonce' => null,
                    'adminEsEnable' => true,
                    'storefrontEsEnable' => true,
                    'serviceRegistryUrl' => '',
                    'refreshTokenTtl' => 7 * 86400 * 1000,
                    'productStreamIndexingEnabled' => true,
                    'analyticsGatewayUrl' => '',
                ]
            );

        $container->set('twig', $twig);
        $controller->setContainer($container);

        $currencyCollection = new CurrencyCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('fakeIsoCode');
        $currencyCollection->add($currency);

        $currencyRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'currency',
                1,
                $currencyCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $response = $controller->index(new Request(), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testIndexSetsCacheHeaders(): void
    {
        $this->parameterBag->method('has')->willReturn(true);
        $this->parameterBag->method('get')->willReturn(true);

        $currencyRepository = $this->createMock(EntityRepository::class);

        $controller = $this->createAdministrationController(currencyRepository: $currencyRepository);

        $container = new Container();
        $twig = $this->createMock(Environment::class);

        $twig->expects($this->once())->method('render')
            ->willReturn('<html></html>');

        $container->set('twig', $twig);
        $controller->setContainer($container);

        $currencyCollection = new CurrencyCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');
        $currencyCollection->add($currency);

        $currencyRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'currency',
                1,
                $currencyCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $response = $controller->index(new Request(), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($response->headers->has('cache-control'));

        $cacheControl = $response->headers->get('cache-control');
        static::assertNotNull($cacheControl);
        static::assertStringContainsString('max-age=0', $cacheControl);
        static::assertStringContainsString('public', $cacheControl);
        static::assertStringContainsString('stale-while-revalidate=86400', $cacheControl);

        // @deprecated tag:v6.8.0 - remove whole block
        if (Feature::isActive('v6.8.0.0')) {
            static::assertFalse($response->headers->has(AdministrationController::CACHE_ID_HEADER));
        } else {
            static::assertSame(AdministrationController::CACHE_ID_ADMINISTRATION, $response->headers->get(AdministrationController::CACHE_ID_HEADER));
        }
    }

    public function testIndexOmitsStaleWhileRevalidateWhenFrwIsActive(): void
    {
        $this->parameterBag->method('has')->willReturn(true);
        $this->parameterBag->method('get')->willReturn(true);

        $frwService = static::createStub(FirstRunWizardService::class);
        $frwService->method('frwShouldRun')->willReturn(true);

        $currencyRepository = $this->createMock(EntityRepository::class);

        $controller = $this->createAdministrationController(firstRunWizardService: $frwService, currencyRepository: $currencyRepository);

        $container = new Container();
        $twig = $this->createMock(Environment::class);

        $twig->expects($this->once())->method('render')
            ->willReturn('<html></html>');

        $container->set('twig', $twig);
        $controller->setContainer($container);

        $currencyCollection = new CurrencyCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');
        $currencyCollection->add($currency);

        $currencyRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'currency',
                1,
                $currencyCollection,
                null,
                new Criteria(),
                $this->context
            )
        );

        $response = $controller->index(new Request(), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($response->headers->has('cache-control'));

        $cacheControl = $response->headers->get('cache-control');
        static::assertNotNull($cacheControl);
        static::assertStringContainsString('max-age=0', $cacheControl);
        static::assertStringContainsString('public', $cacheControl);
        static::assertStringNotContainsString('stale-while-revalidate', $cacheControl);
    }

    public function testCheckCustomerEmailValidWithoutException(): void
    {
        $controller = $this->createAdministrationController();
        $request = new Request([], ['email' => 'random@email.com']);

        $response = $controller->checkCustomerEmailValid($request, $this->context);
        static::assertNotFalse($response->getContent());
        static::assertSame(
            json_encode(['isValid' => true]),
            $response->getContent()
        );
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelIdValid(): void
    {
        $controller = $this->createAdministrationController(new CustomerCollection());
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => Uuid::randomHex()]);

        $response = $controller->checkCustomerEmailValid($request, $this->context);
        static::assertNotFalse($response->getContent());
        static::assertSame(
            json_encode(['isValid' => true]),
            $response->getContent()
        );
    }

    public function testCheckCustomerEmailValidThrowErrorWithNullEmailParameter(): void
    {
        $this->expectException(RoutingException::class);

        $controller = $this->createAdministrationController();
        $request = new Request();

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithConstraintException(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->buildCustomerEntity();

        $controller = $this->createAdministrationController(new CustomerCollection([$customer]));
        $request = new Request([], ['email' => 'random@email.com']);

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelIdInvalid(): void
    {
        $this->expectException(RoutingException::class);

        $controller = $this->createAdministrationController(new CustomerCollection());
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => true]);

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelWithCustomerExistsInSalesChannel(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->buildCustomerEntity();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setName('New Sales Channel');

        $customer->setBoundSalesChannel($salesChannel);

        $controller = $this->createAdministrationController(new CustomerCollection([$customer]));
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => $salesChannel->getId()]);

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelWithCustomerExistsInAllSalesChannel(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->buildCustomerEntity();

        $controller = $this->createAdministrationController(new CustomerCollection([$customer]));
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => Uuid::randomHex()]);

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testKnownIpsReturnsIpsFromRequest(): void
    {
        $controller = $this->createAdministrationController();
        $response = $controller->knownIps(new Request(server: ['REMOTE_ADDR' => '127.0.0.1']));

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"ips":[{"name":"global.sw-multi-tag-ip-select.knownIps.you","value":"127.0.0.1"}]}',
            $response->getContent()
        );
    }

    public function testPluginIndexReturnsNotFoundResponse(): void
    {
        $fileSystemOperator = $this->createMock(PrefixFilesystem::class);

        $controller = $this->createAdministrationController(fileSystemOperator: $fileSystemOperator);

        $fileSystemOperator->expects($this->once())
            ->method('read')
            ->with('bundles/foo/meteor-app/index.html')
            ->willThrowException(new UnableToReadFile());
        $response = $controller->pluginIndex('foo');

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('Plugin index.html not found', $response->getContent());
    }

    public function testPluginIndexReturnsUnchangedFileIfNoReplaceableStringIsFound(): void
    {
        $fileSystemOperator = $this->createMock(PrefixFilesystem::class);

        $controller = $this->createAdministrationController(fileSystemOperator: $fileSystemOperator);

        $fileContent = '<html><head></head><body></body></html>';
        $fileSystemOperator->expects($this->once())
            ->method('read')
            ->with('bundles/foo/meteor-app/index.html')
            ->willReturn($fileContent);
        $response = $controller->pluginIndex('foo');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame($fileContent, $response->getContent());
    }

    public function testPluginIndexReplacesAsset(): void
    {
        $fileSystemOperator = $this->createMock(PrefixFilesystem::class);

        $controller = $this->createAdministrationController(fileSystemOperator: $fileSystemOperator);

        $fileContent = '<html><head><base href="__$ASSET_BASE_PATH$__" /></head><body></body></html>';
        $fileSystemOperator->expects($this->once())
            ->method('read')
            ->with('bundles/foo/meteor-app/index.html')
            ->willReturn($fileContent);

        $fileSystemOperator->expects($this->once())
            ->method('publicUrl')
            ->with('/')
            ->willReturn('http://localhost/bundles/');

        $response = $controller->pluginIndex('foo');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringNotContainsString('__$ASSET_BASE_PATH$__', $content);
        static::assertStringContainsString('http://localhost/bundles/', $content);
    }

    public function testPluginIndexSetsCacheHeaders(): void
    {
        $fileSystemOperator = $this->createMock(PrefixFilesystem::class);

        $controller = $this->createAdministrationController(fileSystemOperator: $fileSystemOperator);

        $fileContent = '<html><head></head><body></body></html>';
        $fileSystemOperator->expects($this->once())
            ->method('read')
            ->with('bundles/test-plugin/meteor-app/index.html')
            ->willReturn($fileContent);

        $fileSystemOperator->expects($this->once())
            ->method('publicUrl')
            ->with('/')
            ->willReturn('http://localhost/bundles/');

        $response = $controller->pluginIndex('test-plugin');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertTrue($response->headers->has('cache-control'));

        $cacheControl = $response->headers->get('cache-control');
        static::assertNotNull($cacheControl);
        static::assertStringContainsString('max-age=0', $cacheControl);
        static::assertStringContainsString('public', $cacheControl);
        static::assertStringContainsString('stale-while-revalidate=86400', $cacheControl);

        // @deprecated tag:v6.8.0 - CACHE_ID_HEADER is only emitted while the cache rework is inactive; the whole
        // block is removed together with the CacheControlListener.
        if (Feature::isActive('v6.8.0.0')) {
            static::assertFalse($response->headers->has(AdministrationController::CACHE_ID_HEADER));
        } else {
            static::assertSame(AdministrationController::CACHE_ID_ADMINISTRATION, $response->headers->get(AdministrationController::CACHE_ID_HEADER));
        }
    }

    public function testResetExcludedSearchTermThrowsRoutingException(): void
    {
        $this->expectExceptionObject(RoutingException::languageNotFound($this->context->getLanguageId()));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn(false);
        $controller = $this->createAdministrationController(connection: $connection);

        $controller->resetExcludedSearchTerm($this->context);
    }

    #[DataProvider('excludedTerms')]
    public function testResetExcludedSearchTerm(
        ?string $sourceLanguage,
        string|false $deLanguageId,
        string|false $enLanguageId,
        Context $context
    ): void {
        $excludedTerms = $this->getExcludedTerms($sourceLanguage);
        $searchConfigId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls($searchConfigId, $deLanguageId, $enLanguageId);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        if ($sourceLanguage === null) {
            $eventDispatcher->expects($this->once())->method('dispatch')
                ->willReturn(new PreResetExcludedSearchTermEvent($searchConfigId, $excludedTerms, $context));
        } else {
            $eventDispatcher->expects($this->never())->method('dispatch');
        }

        $connection->expects($this->once())->method('executeStatement')
            ->with(
                'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
                [
                    'excludedTerms' => json_encode($excludedTerms, \JSON_THROW_ON_ERROR),
                    'id' => $searchConfigId,
                ]
            );

        $controller = $this->createAdministrationController(connection: $connection, eventDispatcher: $eventDispatcher);

        $response = $controller->resetExcludedSearchTerm($context);

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"success":true}', $response->getContent());
    }

    public function testSanitizeHtmlThrowsRoutingExceptionWhenMissingParameter(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('html'));

        $controller = $this->createAdministrationController();

        $controller->sanitizeHtml(new Request(), $this->context);
    }

    public function testSanitizeHtmlInvokesSanitizerWhenFieldIsEmpty(): void
    {
        $htmlSanitizer = $this->createMock(HtmlSanitizer::class);
        $htmlSanitizer->expects($this->once())->method('sanitize')->willReturn('');

        $controller = $this->createAdministrationController(htmlSanitizer: $htmlSanitizer);
        $response = $controller->sanitizeHtml(new Request([], ['html' => '<br/>', 'field' => '']), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":""}', $response->getContent());
    }

    public function testSanitizeHtmlThrowsRoutingExceptionWhenPropertyIsNotFound(): void
    {
        $field = 'test_entity.unknownProperty';
        $this->expectExceptionObject(RoutingException::invalidRequestParameter($field));

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($definitionRegistry);
        $definitionRegistry->expects($this->once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController(definitionRegistry: $definitionRegistry);
        $controller->sanitizeHtml(new Request([], ['html' => '<br/>', 'field' => $field]), $this->context);
    }

    public function testSanitizeHtmlStripsTagsWhenPropertyHTMLIsIsNotAllowed(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($definitionRegistry);
        $definitionRegistry->expects($this->once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController(definitionRegistry: $definitionRegistry);
        $response = $controller->sanitizeHtml(new Request([], ['html' => '<p>test</p>', 'field' => 'test_entity.id']), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":"test"}', $response->getContent());
    }

    public function testSanitizeHtmlReturnsRawHTMLWhenHTMLIsAllowedAndFlagIsNotSanitized(): void
    {
        $html = '<p>test</p>';
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($definitionRegistry);
        $definitionRegistry->expects($this->once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController(definitionRegistry: $definitionRegistry);
        $response = $controller->sanitizeHtml(new Request([], ['html' => $html, 'field' => 'test_entity.idAllowHtml']), $this->context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":"' . $html . '"}', $response->getContent());
    }

    public function testSanitizeHtmlInvokesSanitizerWhenHTMLIsAllowedAndFlagIsSanitized(): void
    {
        $sanitized = 'test';
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($definitionRegistry);
        $definitionRegistry->expects($this->once())->method('getByEntityName')->willReturn($entityDefinition);

        $htmlSanitizer = $this->createMock(HtmlSanitizer::class);
        $htmlSanitizer->expects($this->once())->method('sanitize')->willReturn($sanitized);

        $controller = $this->createAdministrationController(htmlSanitizer: $htmlSanitizer, definitionRegistry: $definitionRegistry);
        $response = $controller->sanitizeHtml(
            new Request([], ['html' => '<p>test</p>', 'field' => 'test_entity.idAllowHtmlSanitized']),
            $this->context
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":"' . $sanitized . '"}', $response->getContent());
    }

    public function testSnippetFinderAddsEnglishSnippetWhenLocaleIsDifferent(): void
    {
        $controller = $this->createAdministrationController();

        $response = $controller->snippets(new Request(query: ['locale' => 'de-DE']));

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"de-DE":[],"en-GB":[]}', $response->getContent());
    }

    public function testGetUnauthenticatedSnippetsWithoutAuthentication(): void
    {
        $controller = $this->createUnauthenticatedAdministrationController();

        $request = new Request(query: ['locale' => 'en-GB']);
        $response = $controller->snippets($request);
        $snippets = \json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR)['en-GB'];

        static::assertCount(2, $snippets);
        static::assertArrayHasKey('global', $snippets);
        static::assertArrayHasKey('sw-login', $snippets);
    }

    public function testGetAllActivatedLanguagesLocales(): void
    {
        $expectedLocales = [
            $this->ids->create('de-DE') => 'de-DE',
            $this->ids->create('en-GB') => 'en-GB',
            $this->ids->create('jp-JP') => 'jp-JP',
        ];

        $languages = \array_map(static function (string $locale, string $languageId) {
            $localeEntity = new LocaleEntity();
            $localeEntity->setCode($locale);

            $languageEntity = new LanguageEntity();
            $languageEntity->setId($languageId);
            $languageEntity->setLocale($localeEntity);

            return $languageEntity;
        }, $expectedLocales, \array_keys($expectedLocales));

        $context = Context::createDefaultContext();
        $languageRepository = static::createStub(EntityRepository::class);
        $languageRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'language',
                2,
                new LanguageCollection($languages),
                null,
                new Criteria(),
                $context,
            ));
        $controller = $this->createAdministrationController(null, $languageRepository);

        $jsonResponse = $controller->getLocales(new Request(), $context);
        static::assertInstanceOf(JsonResponse::class, $jsonResponse);
        static::assertSame(Response::HTTP_OK, $jsonResponse->getStatusCode());

        $content = $jsonResponse->getContent();
        static::assertNotFalse($content);

        $actualLocales = \json_decode($content, true);
        static::assertEquals($expectedLocales, $actualLocales);
    }

    public static function excludedTerms(): \Generator
    {
        $languageId = Uuid::fromStringToHex('languageId');

        yield 'default excluded terms' => [
            null,
            false,
            false,
            new Context(new SystemSource(), [], Defaults::CURRENCY),
        ];

        yield 'english excluded terms' => [
            'en',
            false,
            Uuid::fromHexToBytes($languageId),
            new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]),
        ];

        yield 'german excluded terms' => [
            'de',
            Uuid::fromHexToBytes($languageId),
            false,
            new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]),
        ];
    }

    /**
     * @param ?EntityRepository<LanguageCollection> $languageRepository
     * @param ?EntityRepository<CurrencyCollection> $currencyRepository
     */
    protected function createAdministrationController(
        ?CustomerCollection $collection = null,
        ?EntityRepository $languageRepository = null,
        (SnippetFinderInterface&MockObject)|null $snippetFinder = null,
        (SymfonyBearerTokenValidator&MockObject)|null $tokenValidator = null,
        ?FirstRunWizardService $firstRunWizardService = null,
        ?Connection $connection = null,
        ?EntityRepository $currencyRepository = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?HtmlSanitizer $htmlSanitizer = null,
        ?DefinitionInstanceRegistry $definitionRegistry = null,
        ?PrefixFilesystem $fileSystemOperator = null,
        ?AirGappedMode $airGappedMode = null,
    ): AdministrationController {
        $collection = $collection ?? new CustomerCollection();

        $customerRepository = new StaticEntityRepository([$collection]);
        $customerEmailUniqueChecker = static::createStub(CustomerEmailUniqueChecker::class);
        $customerEmailUniqueChecker
            ->method('findConflictingCustomerId')
            ->willReturn($collection->first()?->getId());

        return new AdministrationController(
            static::createStub(TemplateFinder::class),
            $firstRunWizardService ?? static::createStub(FirstRunWizardService::class),
            $snippetFinder ?? static::createStub(SnippetFinderInterface::class),
            [],
            new KnownIpsCollector(),
            $connection ?? $this->connection,
            $eventDispatcher ?? $this->eventDispatcher,
            $this->shopwareCoreDir,
            $customerRepository,
            $currencyRepository ?? $this->currencyRepository,
            $htmlSanitizer ?? $this->htmlSanitizer,
            $definitionRegistry ?? $this->definitionRegistry,
            $this->parameterBag,
            $fileSystemOperator ?? $this->fileSystemOperator,
            $this->serviceRegistryUrl,
            $languageRepository ?? $this->languageRepository,
            $tokenValidator ?? static::createStub(SymfonyBearerTokenValidator::class),
            $this->analyticsGatewayUrl,
            $customerEmailUniqueChecker,
            $airGappedMode ?? new AirGappedMode(false),
            $this->refreshTokenTtl,
        );
    }

    private function createUnauthenticatedAdministrationController(): AdministrationController
    {
        /** @var SnippetFinderInterface&MockObject $snippetFinder */
        $snippetFinder = $this->createMock(SnippetFinderInterface::class);
        $snippetFinder
            ->expects($this->once())
            ->method('findSnippets')
            ->willReturn([
                'global' => [],
                'sw-login' => [],
                'entityCategories' => [],
                'help-center' => [],
                'locale' => [],
                'mt-text-editor-toolbar-button-link' => [],
                'sales-channel-theme' => [],
                'sidebar' => [],
                'sw-ai-copilot-warning' => [],
                'sw-app' => [],
                'sw-base-filter' => [],
                'sw-boolean-filter' => [],
                'sw-bulk-edit' => [],
                'sw-category' => [],
                'sw-category-tree-field' => [],
                'sw-cms' => [],
                'sw-config-form-renderer' => [],
            ]);

        $tokenValidator = $this->createMock(SymfonyBearerTokenValidator::class);
        $tokenValidator
            ->expects($this->once())
            ->method('validateAuthorization')
            ->willThrowException(new OAuthServerException('', 0, ''));

        return $this->createAdministrationController(
            null,
            null,
            $snippetFinder,
            $tokenValidator,
        );
    }

    private function buildCustomerEntity(): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        return $customer;
    }

    /**
     * @return string[]
     */
    private function getExcludedTerms(?string $language): array
    {
        if (!\in_array($language, ['de', 'en'], true)) {
            return [];
        }

        return require $this->shopwareCoreDir . '/Migration/Fixtures/stopwords/' . $language . '.php';
    }
}
