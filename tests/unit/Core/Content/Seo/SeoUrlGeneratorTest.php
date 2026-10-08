<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlGenerator::class)]
class SeoUrlGeneratorTest extends TestCase
{
    private const TEST_ENTITY_NAME = 'seo_test_entity';

    private Context $context;

    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId('sales-channel-id');
        $this->salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function testGenerateProducesSeoUrlWithCorrectFields(): void
    {
        $entity = $this->createTestEntity();

        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment();

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/base/path-info');

        $request = Request::create('/base/path-info');
        $request->server->set('SCRIPT_NAME', '/base/index.php');
        $requestStack = static::createStub(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $config = new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '  seo-path  ', true);
        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn($config);
        $route->expects($this->once())
            ->method('getMapping')
            ->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], ['name' => 'seo'], 'mapping-warning'));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            new NullLogger(),
            $router,
            $requestStack
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '  seo-path  ', $route, $this->context, $this->salesChannel), false);

        static::assertCount(1, $urls);
        static::assertSame('entity-1', $urls[0]->getForeignKey());
        static::assertSame('mapping-warning', $urls[0]->getError());
        static::assertSame('/base/path-info', $urls[0]->getPathInfo());
        static::assertSame('seo-path', $urls[0]->getSeoPathInfo());
        static::assertSame($this->salesChannel->getId(), $urls[0]->getSalesChannelId());
    }

    public function testGenerateForHeadlessStoresRelativeSeoPathInfo(): void
    {
        $entity = $this->createTestEntity();

        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment();

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/store-api/product/entity-1');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'store-api.product.detail', 'seo-path', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], ['name' => 'seo']));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            new NullLogger(),
            $router,
            new RequestStack()
        );

        $urls = iterator_to_array(
            $generator->generate(['entity-1'], 'seo-path', $route, $this->context, $this->createHeadlessSalesChannel(true)),
            false
        );

        static::assertCount(1, $urls);
        static::assertSame('seo-path', $urls[0]->getSeoPathInfo());
    }

    public function testGenerateForHeadlessWithoutExternalStorefrontDomainReturnsNothing(): void
    {
        $entityRepository = new StaticEntityRepository([], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $twig = $this->createTwigEnvironment();

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'store-api.product.detail', 'seo-path', true));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser
        );

        $urls = iterator_to_array(
            $generator->generate(['entity-1'], 'seo-path', $route, $this->context, $this->createHeadlessSalesChannel(false)),
            false
        );

        static::assertCount(0, $urls);
    }

    public function testGenerateSkipsEmptySeoPathInfo(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment();

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '   ', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], ['name' => 'seo']));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            new NullLogger(),
            $router,
            new RequestStack()
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '   ', $route, $this->context, $this->salesChannel), false);

        static::assertCount(0, $urls);
    }

    public function testGenerateYieldsAnErrorWhenTheTemplateRendersAnEmptyPath(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{{ name }}', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], ['name' => '']));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $this->createTwigEnvironment(),
            $parser,
            new NullLogger(),
            $router,
            new RequestStack()
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ name }}', $route, $this->context, $this->salesChannel), false);

        // Dropping the entity instead would exclude it from the persisted set, which makes
        // SeoUrlPersister mark its existing SEO URL as deleted.
        static::assertCount(1, $urls);
        static::assertSame('entity-1', $urls[0]->getForeignKey());
        static::assertSame('', $urls[0]->getSeoPathInfo());
        static::assertSame('The SEO URL template rendered an empty path', $urls[0]->getError());
    }

    public function testGenerateKeepsTheMappingErrorWhenTheTemplateRendersAnEmptyPath(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{{ name }}', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], ['name' => ''], 'not available for sales channel'));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $this->createTwigEnvironment(),
            $parser,
            new NullLogger(),
            $router,
            new RequestStack()
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ name }}', $route, $this->context, $this->salesChannel), false);

        static::assertCount(1, $urls);
        static::assertSame('not available for sales channel', $urls[0]->getError());
    }

    public function testGenerateSkipsInvalidTemplateIfConfigured(): void
    {
        $entityRepository = new StaticEntityRepository([], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $twig = $this->createTwigEnvironment();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{% for value in %}', true));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            $logger
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '{% for value in %}', $route, $this->context, $this->salesChannel), false);

        static::assertCount(0, $urls);
    }

    public function testGenerateThrowsOnInvalidTemplateIfNotConfiguredToSkip(): void
    {
        $entityRepository = new StaticEntityRepository([], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $twig = $this->createTwigEnvironment();

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{% for value in %}', false));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser
        );

        $this->expectExceptionObject(new InvalidTemplateException('Syntax error'));
        iterator_to_array($generator->generate(['entity-1'], '{% for value in %}', $route, $this->context, $this->salesChannel), false);
    }

    public function testGenerateFlagsRenderingErrorsIfConfiguredToSkipInvalid(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment(strict: true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{{ missing.value }}', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], []));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            $logger,
            $router
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);

        // Skipping invalid templates must not drop the entity: that would exclude it from
        // the persisted set and mark its existing SEO URL as deleted.
        static::assertCount(1, $urls);
        static::assertSame('', $urls[0]->getSeoPathInfo());
        static::assertSame('The SEO URL template could not be rendered', $urls[0]->getError());
    }

    public function testGenerateThrowsOnRenderingErrorIfNotConfiguredToSkip(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment(strict: true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{{ missing.value }}', false));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], []));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            new NullLogger(),
            $router
        );

        $this->expectExceptionObject(new InvalidTemplateException('Error:'));
        iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);
    }

    public function testGenerateThrowsExceptionWhileParsingTemplate(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
        ], $this->createTestDefinition());
        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willThrowException(new \Exception('broken parser'));
        $twig = $this->createTwigEnvironment(true);
        $router = static::createStub(RouterInterface::class);
        $requestStack = new RequestStack();
        $generator = $this->createGenerator([self::TEST_ENTITY_NAME => $entityRepository], $twig, $parser, null, $router, $requestStack);
        $this->expectException(InvalidTemplateException::class);
        \iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', static::createStub(SeoUrlRouteInterface::class), $this->context, $this->salesChannel), false);
    }

    public function testGenerateWithLastFieldHasRuntimeFlag(): void
    {
        $entity = $this->createTestEntity();
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());
        $parser = static::createStub(TwigVariableParser::class);
        $parser->method('parse')->willReturn(['testRuntime']);
        $twig = $this->createTwigEnvironment();
        $router = static::createStub(RouterInterface::class);
        $requestStack = new RequestStack();
        $generator = $this->createGenerator([self::TEST_ENTITY_NAME => $entityRepository], $twig, $parser, null, $router, $requestStack);
        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), ProductPageSeoUrlRoute::ROUTE_NAME, '{{ missing.value }}', true));
        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);
        static::assertCount(1, $urls);
        static::assertSame('The SEO URL template could not be rendered', $urls[0]->getError());
    }

    private function createHeadlessSalesChannel(bool $externalStorefront): SalesChannelEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('domain-1');
        $domain->setUrl('https://headless.example');
        $domain->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $domain->setIsExternalStorefront($externalStorefront);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('headless-sales-channel-id');
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_API);
        $salesChannel->setDomains(new SalesChannelDomainCollection([$domain]));

        return $salesChannel;
    }

    /**
     * @param array<string, mixed> $repositories
     */
    private function createGenerator(
        array $repositories,
        ?Environment $twig = null,
        ?TwigVariableParser $parser = null,
        ?LoggerInterface $logger = null,
        ?RouterInterface $router = null,
        ?RequestStack $requestStack = null
    ): SeoUrlGenerator {
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getRepository')->willReturn($repositories[self::TEST_ENTITY_NAME]);

        $twig ??= static::createStub(Environment::class);
        $parser ??= static::createStub(TwigVariableParser::class);
        $router ??= static::createStub(RouterInterface::class);
        $requestStack ??= new RequestStack();
        $logger ??= new NullLogger();

        $parserFactory = static::createStub(TwigVariableParserFactory::class);
        $parserFactory->method('getParser')->willReturn($parser);

        return new SeoUrlGenerator(
            $definitionRegistry,
            $router,
            $requestStack,
            $twig,
            $parserFactory,
            $logger
        );
    }

    private function createTwigEnvironment(bool $strict = false): Environment
    {
        $twig = new Environment(new ArrayLoader());
        $twig->getRuntime(EscaperRuntime::class)->setEscaper(
            SeoUrlGenerator::ESCAPE_SLUGIFY,
            static fn (string $value): string => $value
        );

        if ($strict) {
            $twig->enableStrictVariables();
        }

        return $twig;
    }

    private function createTestEntity(): ArrayEntity
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entity->internalSetEntityData(self::TEST_ENTITY_NAME, new FieldVisibility([]));

        return $entity;
    }

    private function createTestDefinition(): EntityDefinition
    {
        return new class extends EntityDefinition {
            public function getEntityName(): string
            {
                return 'seo_test_entity';
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
                    (new StringField('testRuntime', 'testRuntime'))->addFlags(new Runtime()),
                ]);
            }
        };
    }
}
