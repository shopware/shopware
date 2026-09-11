<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use Doctrine\DBAL\Connection;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentAppTemplateTest extends TestCase
{
    private const PAGE = 'storefront/page.html.twig';

    /**
     * @var array<string, string>
     */
    private array $appTemplates = [];

    private EntityTemplateLoader $appLoader;

    private ArrayLoader $filesystemLoader;

    private TwigEnvironment $twig;

    private TemplateFinder $finder;

    private TestHandler $logs;

    private RequestStack $requests;

    protected function setUp(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(function (): array {
            $rows = [];
            foreach ($this->appTemplates as $name => $template) {
                [$namespace, $path] = explode('/', substr($name, 1), 2);
                $rows[] = ['namespace' => $namespace, 'path' => $path, 'template' => $template, 'hash' => hash('sha256', $template), 'updatedAt' => null];
            }

            return $rows;
        });
        $this->appLoader = new EntityTemplateLoader($connection, 'prod');
        $this->filesystemLoader = new ArrayLoader([
            '@Storefront/' . self::PAGE => 'before|{% block content %}core{% endblock %}|after',
        ]);
        $loader = new ChainLoader([$this->appLoader, $this->filesystemLoader]);
        $this->twig = new TwigEnvironment($loader);
        $hierarchy = static::createStub(NamespaceHierarchyBuilder::class);
        $hierarchy->method('buildHierarchy')->willReturn(['HealthyApp' => 2, 'BrokenApp' => 1, 'Storefront' => 0]);
        $this->requests = new RequestStack();
        $scopes = new TemplateScopeDetector($this->requests);
        $this->finder = new TemplateFinder($this->twig, $loader, '', $hierarchy, $scopes);
        $this->twig->addExtension(new NodeExtension($this->finder, $scopes));
        $this->twig->addExtension(new TwigFeaturesWithInheritanceExtension($this->finder));
        $this->logs = new TestHandler();
        $this->twig->configureAppTemplateFailureHandling($this->appLoader, new Logger('test', [$this->logs]), $this->requests);
    }

    public function testSyntaxErrorFallsBackToCoreAndLogsTheAppTemplate(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{% broken %}';

        static::assertSame('before|core|after', $this->twig->render($name));
        $records = $this->logs->getRecords();
        static::assertCount(1, $records);
        static::assertSame($name, $records[0]->context['template']);
        static::assertInstanceOf(SyntaxError::class, $records[0]->context['exception']);
        static::assertSame([], $this->appLoader->getDisabledApps());
    }

    public function testRuntimeErrorDiscardsPartialOutputAndKeepsHealthyOverrides(): void
    {
        $this->appTemplates['@HealthyApp/' . self::PAGE] = '{% sw_extends "@Storefront/' . self::PAGE . '" %}{% block content %}healthy[{{ parent() }}]{% endblock %}';
        $this->appTemplates['@BrokenApp/' . self::PAGE] = '{% sw_extends "@Storefront/' . self::PAGE . '" %}{% block content %}partial{{ 1 / divisor }}{% endblock %}';
        $name = $this->finder->find('@Storefront/' . self::PAGE);

        // Warm the parent/block instances before exercising the failure path.
        static::assertSame('before|healthy[partial1]|after', $this->twig->render($name, ['divisor' => 1]));
        static::assertSame('before|healthy[core]|after', $this->twig->render($name, ['divisor' => 0]));
        static::assertCount(1, $this->logs->getRecords());
        // A failure with one context must not disable the app for subsequent renders.
        static::assertSame('before|healthy[partial1]|after', $this->twig->render($name, ['divisor' => 1]));
    }

    public function testMissingIncludeInAppFallsBack(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{% sw_extends "@Storefront/' . self::PAGE . '" %}{% block content %}{% include "missing.html.twig" %}{% endblock %}';

        static::assertSame('before|core|after', $this->twig->render($name));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testNestedAppIncludeFailureOmitsTheWholeFailingApp(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{% sw_extends "@Storefront/' . self::PAGE . '" %}{% block content %}partial{% include "@BrokenApp/storefront/fragment.html.twig" %}{% endblock %}';
        $this->appTemplates['@BrokenApp/storefront/fragment.html.twig'] = '{{ 1 / 0 }}';

        static::assertSame('before|core|after', $this->twig->render($name));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testMultipleBrokenAppsEachGetOneRetry(): void
    {
        $this->appTemplates['@HealthyApp/' . self::PAGE] = '{% broken %}';
        $this->appTemplates['@BrokenApp/' . self::PAGE] = '{{ 1 / 0 }}';

        static::assertSame('before|core|after', $this->twig->render($this->finder->find('@Storefront/' . self::PAGE)));
        static::assertCount(2, $this->logs->getRecords());
    }

    public function testStandaloneAppFragmentWithoutFallbackBecomesEmpty(): void
    {
        $this->filesystemLoader->setTemplate('page', 'before|{% include "@BrokenApp/storefront/fragment.html.twig" %}|after');
        $this->appTemplates['@BrokenApp/storefront/fragment.html.twig'] = 'partial{{ 1 / 0 }}';

        static::assertSame('before||after', $this->twig->render('page'));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testSwIncludeTagCanOmitAnAppFragmentWithoutFallback(): void
    {
        $this->filesystemLoader->setTemplate('page', 'before|{% sw_include "@Storefront/storefront/fragment.html.twig" %}|after');
        $this->appTemplates['@BrokenApp/storefront/fragment.html.twig'] = 'partial{{ 1 / 0 }}';

        static::assertSame('before||after', $this->twig->render('page'));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testSwIncludeFunctionCanOmitAnExplicitAppFragment(): void
    {
        $this->filesystemLoader->setTemplate('page', 'before|{{ sw_include("@BrokenApp/storefront/fragment.html.twig") }}|after');
        $this->appTemplates['@BrokenApp/storefront/fragment.html.twig'] = 'partial{{ 1 / 0 }}';

        static::assertSame('before||after', $this->twig->render('page'));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testOmittingAnAppDoesNotHideAnUnrelatedMissingCoreTemplate(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->filesystemLoader->setTemplate('@Storefront/' . self::PAGE, '{% sw_include "@Storefront/storefront/missing.html.twig" %}');
        $this->appTemplates[$name] = '{% broken %}';

        $this->expectException(LoaderError::class);

        try {
            $this->twig->render($name);
        } finally {
            static::assertCount(1, $this->logs->getRecords());
        }
    }

    public function testPreviouslyLoadedTemplateWrapperIsReloadedForRetry(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{{ 1 / 0 }}';
        $wrapper = $this->twig->load($name);

        static::assertSame('before|core|after', $this->twig->render($wrapper));
    }

    public function testEmbeddedTemplateFailureUsesTheFallbackAndCanRecover(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{% embed "@Storefront/' . self::PAGE . '" %}{% block content %}partial{{ 1 / divisor }}{% endblock %}{% endembed %}';

        static::assertSame('before|partial1|after', $this->twig->render($name, ['divisor' => 1]));
        static::assertSame('before|core|after', $this->twig->render($name, ['divisor' => 0]));
        static::assertSame('before|partial1|after', $this->twig->render($name, ['divisor' => 1]));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testCoreErrorIsPropagatedWithoutLoggingAnAppFailure(): void
    {
        $this->filesystemLoader->setTemplate('@Storefront/' . self::PAGE, '{{ 1 / 0 }}');
        $this->appTemplates['@BrokenApp/' . self::PAGE] = '{% sw_extends "@Storefront/' . self::PAGE . '" %}';

        $this->expectException(RuntimeError::class);

        try {
            $this->twig->render('@BrokenApp/' . self::PAGE);
        } finally {
            static::assertSame([], $this->logs->getRecords());
            static::assertSame([], $this->appLoader->getDisabledApps());
        }
    }

    public function testFailureInCoreFallbackStillPropagatesAndRestoresApps(): void
    {
        $name = '@BrokenApp/' . self::PAGE;
        $this->filesystemLoader->setTemplate('@Storefront/' . self::PAGE, '{{ 1 / 0 }}');
        $this->appTemplates[$name] = '{% broken %}';

        $this->expectException(RuntimeError::class);

        try {
            $this->twig->render($name);
        } finally {
            static::assertCount(1, $this->logs->getRecords());
            static::assertTrue($this->appLoader->exists($name));
        }
    }

    public function testExceptionsFromAppTwigFunctionsAreIsolated(): void
    {
        $this->twig->addFunction(new TwigFunction('fail', static function (): never {
            throw new \RuntimeException('App rendering failed');
        }));
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{{ fail() }}';

        static::assertSame('before|core|after', $this->twig->render($name));
        static::assertCount(1, $this->logs->getRecords());
    }

    public function testDegradedPagesCannotBeStoredInTheHttpCache(): void
    {
        $main = new Request();
        $sub = new Request();
        $this->requests->push($main);
        $this->requests->push($sub);
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = '{% broken %}';

        static::assertSame('before|core|after', $this->twig->render($name));
        static::assertTrue($main->attributes->get(PlatformRequest::ATTRIBUTE_NO_STORE));
        static::assertTrue($sub->attributes->get(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testSuccessfulRenderRemainsCacheable(): void
    {
        $request = new Request();
        $this->requests->push($request);
        $name = '@BrokenApp/' . self::PAGE;
        $this->appTemplates[$name] = 'working';

        static::assertSame('working', $this->twig->render($name));
        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE));
        static::assertSame([], $this->logs->getRecords());
    }
}
