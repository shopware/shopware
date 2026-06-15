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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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
    }

    public function testGenerateProducesSeoUrlWithCorrectFields(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);

        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment();

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/base/path-info');

        $request = Request::create('/base/path-info');
        $request->server->set('SCRIPT_NAME', '/base/index.php');
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn($request);

        $config = new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '  seo-path  ', true);
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

    public function testGenerateSkipsEmptySeoPathInfo(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment();

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '   ', true));
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

    public function testGenerateSkipsInvalidTemplateIfConfigured(): void
    {
        $entityRepository = new StaticEntityRepository([], $this->createTestDefinition());

        $parser = $this->createMock(TwigVariableParser::class);
        $twig = $this->createTwigEnvironment();

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '{% for value in %}', true));

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

        $parser = $this->createMock(TwigVariableParser::class);
        $twig = $this->createTwigEnvironment();

        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '{% for value in %}', false));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser
        );

        $this->expectExceptionObject(new InvalidTemplateException('Syntax error'));
        iterator_to_array($generator->generate(['entity-1'], '{% for value in %}', $route, $this->context, $this->salesChannel), false);
    }

    public function testGenerateSkipsRenderingErrorsIfConfigured(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment(strict: true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '{{ missing.value }}', true));
        $route->method('getMapping')->willReturn(new SeoUrlMapping($entity, ['id' => 'entity-1'], []));

        $generator = $this->createGenerator(
            [self::TEST_ENTITY_NAME => $entityRepository],
            $twig,
            $parser,
            $logger,
            $router
        );

        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);

        static::assertCount(0, $urls);
    }

    public function testGenerateThrowsOnRenderingErrorIfNotConfiguredToSkip(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn([]);

        $twig = $this->createTwigEnvironment(strict: true);

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/path-info');

        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('prepareCriteria');
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '{{ missing.value }}', false));
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
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
        ], $this->createTestDefinition());
        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willThrowException(new \Exception('broken parser'));
        $twig = $this->createTwigEnvironment(true);
        $router = $this->createMock(RouterInterface::class);
        $requestStack = new RequestStack();
        $generator = $this->createGenerator([self::TEST_ENTITY_NAME => $entityRepository], $twig, $parser, null, $router, $requestStack);
        $this->expectException(InvalidTemplateException::class);
        \iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $this->createMock(SeoUrlRouteInterface::class), $this->context, $this->salesChannel), false);
    }

    public function testGenerateWithLastFieldHasRuntimeFlag(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1']);
        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());
        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn(['testRuntime']);
        $twig = $this->createTwigEnvironment();
        $router = $this->createMock(RouterInterface::class);
        $requestStack = new RequestStack();
        $generator = $this->createGenerator([self::TEST_ENTITY_NAME => $entityRepository], $twig, $parser, null, $router, $requestStack);
        $route = $this->createMock(SeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn(new SeoUrlRouteConfig($this->createTestDefinition(), 'frontend.detail.page', '{{ missing.value }}', true));
        $urls = iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);
        static::assertCount(0, $urls);
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
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getRepository')->willReturn($repositories[self::TEST_ENTITY_NAME]);

        $twig ??= $this->createMock(Environment::class);
        $parser ??= $this->createMock(TwigVariableParser::class);
        $router ??= $this->createMock(RouterInterface::class);
        $requestStack ??= new RequestStack();
        $logger ??= new NullLogger();

        $parserFactory = $this->createMock(TwigVariableParserFactory::class);
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
