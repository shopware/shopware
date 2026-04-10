<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Docs\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\DocsException;
use Shopware\Core\DevOps\Docs\Script\ScriptReferenceDataCollector;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade;
use Symfony\Component\Finder\Finder;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(ServiceReferenceGenerator::class)]
class ServiceReferenceGeneratorTest extends TestCase
{
    private Environment&MockObject $twig;

    private string $projectDir;

    private ServiceReferenceGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        foreach ([
            'ValidService',
            'NoScriptServiceTag',
            'InvalidGroupService',
            'MissingDescriptionService',
            'InternalService',
            'DeprecatedService',
            'ValidService2',
            'InternalService2',
            'ValidService3',
            'InvalidGroupService2',
            'ServiceWithShopwareReturnType',
            'ServiceWithNullableReturn',
            'ServiceWithInvalidParamDocBlock',
            'ServiceWithShortExample',
            'ServiceWithDuplicateExample',
            'ServiceWithInvalidParamDoc',
            'ServiceWithNoDocMethod',
            'ServiceWithNoParamDoc',
            'ServiceWithNoReturnDoc',
            'NoDocCommentService',
            'InjectedService',
            'ServiceWithInvalidParamAndDefault',
            'ServiceWithNonExampleTag',
            'ServiceWithMissingExampleFile',
            'ServiceWithScriptServiceReturnType',
        ] as $fixture) {
            require_once __DIR__ . '/_fixtures/' . $fixture . '.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->twig = $this->createMock(Environment::class);
        $this->twig->method('render')->willReturnCallback(static fn ($template, $data) => print_r($data, true));
        $this->projectDir = '/project/root';
        $this->generator = new ServiceReferenceGenerator($this->twig, $this->projectDir);
    }

    protected function tearDown(): void
    {
        ScriptReferenceDataCollector::reset();
        parent::tearDown();
    }

    public function testGenerateReturnsExpectedArray(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ValidService::class]);

        $result = $this->generator->generate();

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        $expectedKey = array_key_first($result);
        static::assertIsString($expectedKey);
        static::assertNotEmpty($result[$expectedKey]);
        static::assertStringContainsString('ValidService', $result[$expectedKey]);
    }

    public function testGetGroupForServiceReturnsCorrectGroup(): void
    {
        $group = $this->generator->getGroupForService(new \ReflectionClass(_fixtures\ValidService::class));
        static::assertSame('data_loading', $group);
    }

    /**
     * @param class-string $fqcn
     */
    #[DataProvider('provideInvalidGroupFixtures')]
    public function testGetGroupForServiceThrows(string $fqcn): void
    {
        $this->expectExceptionObject(DocsException::incorrectGroupForScriptService($fqcn));
        $this->generator->getGroupForService(new \ReflectionClass($fqcn));
    }

    public static function provideInvalidGroupFixtures(): \Generator
    {
        yield 'missing script-service tag' => [_fixtures\NoScriptServiceTag::class];
        yield 'invalid group' => [_fixtures\InvalidGroupService::class];
        yield 'missing description' => [_fixtures\MissingDescriptionService::class];
    }

    public function testGetServicesDataSkipsInternal(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            _fixtures\InternalService2::class,
            _fixtures\ValidService3::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ValidService3', $doc);
        static::assertStringNotContainsString('InternalService2', $doc);
    }

    public function testGetServicesDataThrowsOnInvalidGroup(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\InvalidGroupService2::class]);

        $this->expectExceptionObject(DocsException::incorrectGroupForScriptService(_fixtures\InvalidGroupService2::class));
        $this->generator->generate();
    }

    public function testGetServicesDataSkipsInternalAndHandlesDeprecated(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            _fixtures\InternalService::class,
            _fixtures\DeprecatedService::class,
            _fixtures\ValidService2::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ValidService2', $doc);
        static::assertStringContainsString('DeprecatedService', $doc);
        static::assertStringNotContainsString('InternalService', $doc);
        static::assertStringContainsString('deprecated', strtolower($doc));
    }

    public function testGetServicesDataThrowsOnMissingDocBlockForMethod(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithNoDocMethod::class]);

        $this->expectExceptionObject(DocsException::missingDocBlockForMethod('foo', _fixtures\ServiceWithNoDocMethod::class));
        $this->generator->generate();
    }

    public function testGetServicesDataThrowsOnMissingParamDoc(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithNoParamDoc::class]);

        $this->expectExceptionObject(DocsException::missingDocBlockForMethodParam('bar', 'foo', _fixtures\ServiceWithNoParamDoc::class));
        $this->generator->generate();
    }

    public function testGetServicesDataThrowsOnMissingReturnAnnotation(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithNoReturnDoc::class]);

        $this->expectExceptionObject(DocsException::missingReturnAnnotationForMethod('foo', _fixtures\ServiceWithNoReturnDoc::class));
        $this->generator->generate();
    }

    public function testGetServicesDataHandlesNullableType(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithNullableReturn::class]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('null', $doc);
    }

    public function testGetServicesDataThrowsOnDuplicateExampleFile(): void
    {
        $fixtureDir = __DIR__ . '/_fixtures/duplicate_example_test';
        $finder = new Finder();
        $finder->files()->in($fixtureDir)->ignoreUnreadableDirs();
        $files = iterator_to_array($finder);
        ScriptReferenceDataCollector::setFiles($files);
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithDuplicateExample::class]);
        $generator = new ServiceReferenceGenerator($this->twig, $fixtureDir);

        $this->expectExceptionObject(DocsException::exampleFileNotUnique('foo', _fixtures\ServiceWithDuplicateExample::class, 'ExampleFile.php', array_keys($files)));
        $generator->generate();
    }

    public function testGetServicesDataThrowsOnInvalidParamDoc(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithInvalidParamDoc::class]);

        $this->expectExceptionObject(DocsException::missingDocBlockForMethodParam('bar', 'foo', _fixtures\ServiceWithInvalidParamDoc::class));
        $this->generator->generate();
    }

    public function testGetServicesDataHandlesShopwareReturnType(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithShopwareReturnType::class]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ServiceWithShopwareReturnType', $doc);
        static::assertStringContainsString('`', $doc);
    }

    public function testGetServicesDataHandlesShortExampleFile(): void
    {
        $fixtureDir = __DIR__ . '/_fixtures/short_example_test';
        $finder = new Finder();
        $finder->files()->in($fixtureDir)->ignoreUnreadableDirs();
        ScriptReferenceDataCollector::setFiles(iterator_to_array($finder));
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithShortExample::class]);
        $generator = new ServiceReferenceGenerator($this->twig, $fixtureDir);

        $result = $generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('<?php', $doc);
    }

    public function testGetLinkForClassCoversAllBranches(): void
    {
        static::assertNull($this->generator->getLinkForClass('NonShopware\\Class'));
        $link = $this->generator->getLinkForClass(ServiceReferenceGenerator::class);
        static::assertIsString($link);
        static::assertStringContainsString('github.com', $link);
        static::assertNull($this->generator->getLinkForClass('Shopware\\Does\\Not\\Exist'));
    }

    public function testGetLinkForClassReturnsLocalLinkForScriptService(): void
    {
        $fqcn = SalesChannelRepositoryFacade::class;

        $result = $this->generator->getLinkForClass($fqcn, [$fqcn]);

        static::assertIsString($result);
        static::assertStringStartsWith('./data-loading-script-services-reference', $result);
        static::assertStringContainsString('#saleschannelrepositoryfacade', $result);
    }

    public function testGenerateHandlesInvalidParamDocBlock(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithInvalidParamDocBlock::class]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('invalid', $doc);
        static::assertStringContainsString('This is an invalid param', $doc);
    }

    public function testGetNameReturnsInjectedServiceName(): void
    {
        $fqcn = _fixtures\InjectedService::class;
        ScriptReferenceDataCollector::setShopwareClasses([$fqcn]);
        ScriptReferenceDataCollector::setFiles([]);

        $generator = new class($this->twig, $this->projectDir, $fqcn) extends ServiceReferenceGenerator {
            public function __construct(Environment $twig, string $projectDir, string $fqcn)
            {
                parent::__construct($twig, $projectDir);
                $this->injectedServices[$fqcn] = 'myservice';
            }
        };

        $result = $generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('services.myservice', $doc);
        static::assertStringContainsString('`' . $fqcn . '`', $doc);
    }

    public function testFindScriptServicesThrowsWhenNoServicesFound(): void
    {
        // \Countable has no @script-service tag, so no services are found
        ScriptReferenceDataCollector::setShopwareClasses([\Countable::class]);

        $this->expectExceptionObject(DocsException::noScriptServicesFound());
        $this->generator->generate();
    }

    public function testFindScriptServicesSkipsNonExistentClass(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            \Countable::class,
            _fixtures\ValidService::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ValidService', $doc);
    }

    public function testGetServicesDataHandlesInvalidParamWithDefault(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithInvalidParamAndDefault::class]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('bar', $doc);
        static::assertStringContainsString('\'default\'', $doc);
    }

    public function testGetServiceStubMethodDocsCanBeOverridden(): void
    {
        $generator = new class($this->twig, $this->projectDir) extends ServiceReferenceGenerator {
            protected function getServiceStubMethodDocs(): array
            {
                return [];
            }
        };

        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ValidService::class]);
        $result = $generator->generate();
        $doc = implode('', $result);

        // With no injected services, getName() falls back to backtick form — no 'services.' prefix
        static::assertStringContainsString('ValidService', $doc);
        static::assertStringNotContainsString('services.', $doc);
    }

    public function testGetServicesDataRendersLinkForScriptServiceReturnType(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            _fixtures\ServiceWithScriptServiceReturnType::class,
            _fixtures\InjectedService::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        // InjectedService is in $scriptServices so getLinkForClass returns a relative ./ link
        static::assertStringContainsString('[`Shopware\Tests\Unit\Core\DevOps\Docs\Script\_fixtures\InjectedService`](./data-loading-script-services-reference#injectedservice)', $doc);
    }

    public function testGetServicesDataThrowsOnMissingExampleFile(): void
    {
        ScriptReferenceDataCollector::setFiles([]);
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithMissingExampleFile::class]);

        $this->expectExceptionObject(DocsException::exampleFileNotFound('foo', _fixtures\ServiceWithMissingExampleFile::class, 'NonExistentFile.php'));
        $this->generator->generate();
    }

    public function testGetServicesDataSkipsNonExampleTag(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([_fixtures\ServiceWithNonExampleTag::class]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ServiceWithNonExampleTag', $doc);
    }

    public function testFindScriptServicesSkipsClassWithoutDocComment(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            _fixtures\NoDocCommentService::class,
            _fixtures\ValidService::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringContainsString('ValidService', $doc);
        static::assertStringNotContainsString('NoDocCommentService', $doc);
    }

    public function testFindScriptServicesSkipsClassWithoutScriptServiceTag(): void
    {
        ScriptReferenceDataCollector::setShopwareClasses([
            _fixtures\NoScriptServiceTag::class,
            _fixtures\ValidService::class,
        ]);

        $result = $this->generator->generate();
        $doc = implode('', $result);

        static::assertStringNotContainsString('NoScriptServiceTag', $doc);
        static::assertStringContainsString('ValidService', $doc);
    }
}
