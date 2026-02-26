<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Seo\Exception\InvalidTemplateException;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
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

    public function testGenerateLoadsMissingCmsPageDataAndGeneratesSeoUrl(): void
    {
        $entity = new ArrayEntity(['id' => 'entity-1', 'cmsPageId' => 'cms-1']);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('cms-1');

        $entityRepository = new StaticEntityRepository([
            new EntityCollection([$entity]),
            new EntityCollection(),
        ], $this->createTestDefinition());
        $cmsPageRepository = new StaticEntityRepository([
            new EntityCollection([$cmsPage]),
        ], new CmsPageDefinition());

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
        $route->expects($this->once())
            ->method('prepareCriteria')
            ->willReturnCallback(static fn (\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria $criteria): mixed => $criteria->addAssociation('cmsPage'));
        $route->method('getConfig')->willReturn($config);
        $route->expects($this->once())
            ->method('getMapping')
            ->willReturnCallback(function (ArrayEntity $mappedEntity): SeoUrlMapping {
                static::assertInstanceOf(CmsPageEntity::class, $mappedEntity->get('cmsPage'));

                return new SeoUrlMapping($mappedEntity, ['id' => 'entity-1'], ['name' => 'seo'], 'mapping-warning');
            });

        $generator = $this->createGenerator(
            [
                self::TEST_ENTITY_NAME => $entityRepository,
                CmsPageDefinition::ENTITY_NAME => $cmsPageRepository,
            ],
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

        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage('Syntax error');
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

        $this->expectException(InvalidTemplateException::class);
        $this->expectExceptionMessage('Error:');
        iterator_to_array($generator->generate(['entity-1'], '{{ missing.value }}', $route, $this->context, $this->salesChannel), false);
    }

    public function testGetAssociationsReturnsUniqueAssociationPaths(): void
    {
        $categoryDefinition = new CategoryDefinition();
        new StaticEntityRepository([], $categoryDefinition);

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willReturn(['cmsPageIdSwitched', 'cmsPageIdSwitched']);

        $generator = $this->createGenerator([], parser: $parser);

        /** @var array<string> $associations */
        $associations = $this->invokePrivate($generator, 'getAssociations', ['{{ category.cmsPageIdSwitched }}', $categoryDefinition]);
        static::assertSame([], array_values($associations));
    }

    public function testGetAssociationsThrowsSeoExceptionOnParserFailure(): void
    {
        $categoryDefinition = new CategoryDefinition();
        new StaticEntityRepository([], $categoryDefinition);

        $parser = $this->createMock(TwigVariableParser::class);
        $parser->method('parse')->willThrowException(new \RuntimeException('broken parser'));

        $generator = $this->createGenerator([], parser: $parser);

        $this->expectExceptionObject(SeoException::invalidTemplate('broken parser'));
        $this->invokePrivate($generator, 'getAssociations', ['{{ category.name }}', $categoryDefinition]);
    }

    public function testGetMissingCmsPageDataReturnsEmptyCollectionWhenNoMissingPages(): void
    {
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('cms-1');

        $entities = new EntityCollection([
            new ArrayEntity(['id' => 'entity-1', 'cmsPageId' => null]),
            new ArrayEntity(['id' => 'entity-2', 'cmsPageId' => 'cms-1', 'cmsPage' => $cmsPage]),
        ]);

        $generator = $this->createGenerator([]);

        /** @var EntityCollection<Entity> $result */
        $result = $this->invokePrivate($generator, 'getMissingCmsPageData', [$entities, $this->context]);

        static::assertCount(0, $result);
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
        $definitionRegistry->method('getRepository')->willReturnCallback(static function (string $entityName) use ($repositories): EntityRepository {
            if (!isset($repositories[$entityName])) {
                throw new \RuntimeException(\sprintf('Missing repository for "%s".', $entityName));
            }

            return $repositories[$entityName];
        });

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

    /**
     * @param list<mixed> $arguments
     */
    private function invokePrivate(object $instance, string $methodName, array $arguments = []): mixed
    {
        $method = new \ReflectionMethod($instance, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($instance, $arguments);
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
                ]);
            }
        };
    }
}
