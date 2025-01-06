<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Events\PreResetExcludedSearchTermEvent;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Shopware\Administration\Snippet\SnippetFinderInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\DataAbstractionLayer\TestEntityDefinition;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
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
#[Package('checkout')]
#[CoversClass(AdministrationController::class)]
class AdministrationControllerTest extends TestCase
{
    private MockObject&Connection $connection;

    private Context $context;

    private MockObject&EntityRepository $currencyRepository;

    private MockObject&DefinitionInstanceRegistry $definitionRegistry;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&PrefixFilesystem $fileSystemOperator;

    private MockObject&HtmlSanitizer $htmlSanitizer;

    private MockObject&ParameterBagInterface $parameterBag;

    private string $shopwareCoreDir;

    private string $refreshTokenTtl;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->currencyRepository = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->fileSystemOperator = $this->createMock(PrefixFilesystem::class);
        $this->htmlSanitizer = $this->createMock(HtmlSanitizer::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->shopwareCoreDir = __DIR__ . '/../../../../src/Core/';
        $this->refreshTokenTtl = 'P1W';
    }

    public function testIndexPerformsOnSearchOfCurrency(): void
    {
        $this->parameterBag->expects(static::any())->method('has')->willReturn(true);
        $this->parameterBag->expects(static::any())->method('get')->willReturn(true);

        $controller = $this->createAdministrationController();

        $container = new Container();
        $twig = $this->createMock(Environment::class);

        $twig->expects(static::once())->method('render')
            ->willReturnArgument(0)
            ->with(
                '',
                [
                    'features' => [],
                    'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
                    'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
                    'systemCurrencyId' => Defaults::CURRENCY,
                    'disableExtensions' => false,
                    'systemCurrencyISOCode' => 'fakeIsoCode',
                    'liveVersionId' => Defaults::LIVE_VERSION,
                    'firstRunWizard' => false,
                    'apiVersion' => null,
                    'cspNonce' => null,
                    'adminEsEnable' => true,
                    'storefrontEsEnable' => true,
                    'refreshTokenTtl' => 7 * 86400 * 1000,
                ]
            );

        $container->set('twig', $twig);
        $controller->setContainer($container);

        $currencyCollection = new CurrencyCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('fakeIsoCode');
        $currencyCollection->add($currency);

        $this->currencyRepository->expects(static::once())->method('search')->willReturn(
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
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCheckCustomerEmailValidWithoutException(): void
    {
        $controller = $this->createAdministrationController();
        $request = new Request([], ['email' => 'random@email.com']);

        $response = $controller->checkCustomerEmailValid($request, $this->context);
        static::assertNotFalse($response->getContent());
        static::assertEquals(
            json_encode(['isValid' => true]),
            $response->getContent()
        );
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelIdValid(): void
    {
        $controller = $this->createAdministrationController(new CustomerCollection(), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => Uuid::randomHex()]);

        $response = $controller->checkCustomerEmailValid($request, $this->context);
        static::assertNotFalse($response->getContent());
        static::assertEquals(
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

        $controller = $this->createAdministrationController(new CustomerCollection(), true);
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

        $controller = $this->createAdministrationController(new CustomerCollection([$customer]), true);
        $request = new Request([], ['email' => 'random@email.com', 'boundSalesChannelId' => $salesChannel->getId()]);

        $controller->checkCustomerEmailValid($request, $this->context);
    }

    public function testCheckCustomerEmailValidWithBoundSalesChannelWithCustomerExistsInAllSalesChannel(): void
    {
        static::expectException(ConstraintViolationException::class);

        $customer = $this->buildCustomerEntity();

        $controller = $this->createAdministrationController(new CustomerCollection([$customer]), true);
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
        $controller = $this->createAdministrationController();

        $this->fileSystemOperator->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willThrowException(new UnableToReadFile());
        $response = $controller->pluginIndex('foo');

        static::assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertEquals('Plugin index.html not found', $response->getContent());
    }

    public function testPluginIndexReturnsUnchangedFileIfNoReplaceableStringIsFound(): void
    {
        $controller = $this->createAdministrationController();

        $fileContent = '<html><head></head><body></body></html>';
        $this->fileSystemOperator->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willReturn($fileContent);
        $response = $controller->pluginIndex('foo');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals($fileContent, $response->getContent());
    }

    public function testPluginIndexReplacesAsset(): void
    {
        $controller = $this->createAdministrationController();

        $fileContent = '<html><head><base href="__$ASSET_BASE_PATH$__" /></head><body></body></html>';
        $this->fileSystemOperator->expects(static::once())
            ->method('read')
            ->with('bundles/foo/administration/index.html')
            ->willReturn($fileContent);

        $this->fileSystemOperator->expects(static::once())
            ->method('publicUrl')
            ->with('/')
            ->willReturn('http://localhost/bundles/');

        $response = $controller->pluginIndex('foo');

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringNotContainsString('__$ASSET_BASE_PATH$__', $content);
        static::assertStringContainsString('http://localhost/bundles/', $content);
    }

    public function testResetExcludedSearchTermThrowsRoutingException(): void
    {
        $this->expectExceptionObject(RoutingException::languageNotFound($this->context->getLanguageId()));

        $this->connection->expects(static::once())->method('fetchOne')->willReturn(false);
        $controller = $this->createAdministrationController();

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

        $this->connection->expects(static::any())->method('fetchOne')
            ->willReturnOnConsecutiveCalls($searchConfigId, $deLanguageId, $enLanguageId);

        if ($sourceLanguage === null) {
            $this->eventDispatcher->expects(static::once())->method('dispatch')
                ->willReturn(new PreResetExcludedSearchTermEvent($searchConfigId, $excludedTerms, $context));
        } else {
            $this->eventDispatcher->expects(static::never())->method('dispatch');
        }

        $this->connection->expects(static::once())->method('executeStatement')
            ->with(
                'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
                [
                    'excludedTerms' => json_encode($excludedTerms, \JSON_THROW_ON_ERROR),
                    'id' => $searchConfigId,
                ]
            );

        $controller = $this->createAdministrationController();

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
        $this->htmlSanitizer->expects(static::once())->method('sanitize')->willReturn('');

        $controller = $this->createAdministrationController();
        $response = $controller->sanitizeHtml(new Request([], ['html' => '<br/>', 'field' => '']), $this->context);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":""}', $response->getContent());
    }

    public function testSanitizeHtmlThrowsRoutingExceptionWhenPropertyIsNotFound(): void
    {
        $field = 'test_entity.unknownProperty';
        $this->expectExceptionObject(RoutingException::invalidRequestParameter($field));

        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($this->definitionRegistry);
        $this->definitionRegistry->expects(static::once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController();
        $controller->sanitizeHtml(new Request([], ['html' => '<br/>', 'field' => $field]), $this->context);
    }

    public function testSanitizeHtmlStripsTagsWhenPropertyHTMLIsIsNotAllowed(): void
    {
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($this->definitionRegistry);
        $this->definitionRegistry->expects(static::once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController();
        $response = $controller->sanitizeHtml(new Request([], ['html' => '<p>test</p>', 'field' => 'test_entity.id']), $this->context);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":"test"}', $response->getContent());
    }

    public function testSanitizeHtmlReturnsRawHTMLWhenHTMLIsAllowedAndFlagIsNotSanitized(): void
    {
        $html = '<p>test</p>';
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($this->definitionRegistry);
        $this->definitionRegistry->expects(static::once())->method('getByEntityName')->willReturn($entityDefinition);

        $controller = $this->createAdministrationController();
        $response = $controller->sanitizeHtml(new Request([], ['html' => $html, 'field' => 'test_entity.idAllowHtml']), $this->context);

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"preview":"' . $html . '"}', $response->getContent());
    }

    public function testSanitizeHtmlInvokesSanitizerWhenHTMLIsAllowedAndFlagIsSanitized(): void
    {
        $sanitized = 'test';
        $entityDefinition = new TestEntityDefinition();
        $entityDefinition->compile($this->definitionRegistry);
        $this->definitionRegistry->expects(static::once())->method('getByEntityName')->willReturn($entityDefinition);

        $this->htmlSanitizer->expects(static::once())->method('sanitize')->willReturn($sanitized);

        $controller = $this->createAdministrationController();
        $response = $controller->sanitizeHtml(
            new Request([], ['html' => '<p>test</p>', 'field' => 'test_entity.idAllowHtmlSanitized']),
            $this->context
        );

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
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

    protected function createAdministrationController(
        ?CustomerCollection $collection = null,
        bool $isCustomerBoundToSalesChannel = false
    ): AdministrationController {
        $collection = $collection ?? new CustomerCollection();

        return new AdministrationController(
            $this->createMock(TemplateFinder::class),
            $this->createMock(FirstRunWizardService::class),
            $this->createMock(SnippetFinderInterface::class),
            [],
            new KnownIpsCollector(),
            $this->connection,
            $this->eventDispatcher,
            $this->shopwareCoreDir,
            new StaticEntityRepository([$collection]),
            $this->currencyRepository,
            $this->htmlSanitizer,
            $this->definitionRegistry,
            $this->parameterBag,
            new StaticSystemConfigService([
                'core.systemWideLoginRegistration.isCustomerBoundToSalesChannel' => $isCustomerBoundToSalesChannel,
            ]),
            $this->fileSystemOperator,
            $this->refreshTokenTtl,
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
