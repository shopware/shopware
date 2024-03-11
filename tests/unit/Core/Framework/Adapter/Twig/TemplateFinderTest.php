<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 */
#[CoversClass(TemplateFinder::class)]
class TemplateFinderTest extends TestCase
{
    private NamespaceHierarchyBuilder&MockObject $hierarchyBuilder;

    private LoaderInterface&MockObject $loader;

    private TemplateFinder $finder;

    private TemplateScopeDetector&MockObject $templateScopeDetector;

    private Environment&MockObject $twig;

    protected function setUp(): void
    {
        $this->hierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $this->loader = $this->createMock(LoaderInterface::class);
        $this->templateScopeDetector = $this->createMock(TemplateScopeDetector::class);
        $this->twig = $this->createMock(Environment::class);
        $this->finder = new TemplateFinder(
            $this->twig,
            $this->loader,
            '',
            $this->hierarchyBuilder,
            $this->templateScopeDetector
        );
    }

    #[DataProvider('templateNameProvider')]
    public function testGetTemplateName(string $input, string $expectation): void
    {
        static::assertEquals($expectation, $this->finder->getTemplateName($input));
    }

    /**
     * @param array<int, string> $templateExists
     * @param array<string, bool> $bundles
     */
    #[DataProvider('bundleTemplatesMappingProvider')]
    public function testFind(string $template, bool $ignoreMissing, array $templateExists, array $bundles, ?string $source = null, ?string $expectedTemplate = null): void
    {
        if ($expectedTemplate === null && $ignoreMissing === false) {
            static::expectException(LoaderError::class);
        }

        $templatePath = $this->finder->getTemplateName($template);

        $map = [];

        foreach ($templateExists as $bundleName) {
            $map[] = '@' . $bundleName . '/' . $templatePath;
        }

        $this->loader->expects(static::any())->method('exists')->willReturnCallback(fn (string $template) => \in_array($template, $map, true));

        $this->hierarchyBuilder->expects(static::once())->method('buildHierarchy')->willReturn($bundles);

        $foundTemplate = $this->finder->find($template, $ignoreMissing, $source);

        static::assertEquals($expectedTemplate, $foundTemplate);
    }

    public function testFindModifiesCache(): void
    {
        $this->twig->expects(static::once())->method('getCache')->willReturn($this->createMock(FilesystemCache::class));
        $this->twig->expects(static::once())->method('setCache')->with(static::callback(static function (ConfigurableFilesystemCache $cache) {
            $hash = $cache->generateKey('foo', 'bar');
            $cache->setTemplateScopes(['foo']);

            // template scope has been set
            static::assertEquals($hash, $cache->generateKey('foo', 'bar'));

            // config hash had been set as well
            $cache->setConfigHash('');

            return $hash !== $cache->generateKey('foo', 'bar');
        }));
        $this->templateScopeDetector->expects(static::once())->method('getScopes')->willReturn(['foo']);
        $this->finder->find('', true);
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function templateNameProvider(): iterable
    {
        yield 'with @' => [
            '@Framework/documents/credit_note.html.twig',
            'documents/credit_note.html.twig',
        ];

        yield 'without @' => [
            'Framework/documents/invoice.html.twig',
            'Framework/documents/invoice.html.twig',
        ];
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function bundleTemplatesMappingProvider(): iterable
    {
        $coreBundles = [
            'Elasticsearch' => true,
            'Storefront' => true,
            'Administration' => true,
            'Profiling' => true,
            'SwagTheme' => true,
            'Framework' => true,
        ];

        yield 'template not found with ignoreMissing' => [
            '@Framework/documents/non_existing_template.html.twig',
            true,
            [],
            [],
            null,
            'documents/non_existing_template.html.twig',
        ];

        yield 'template not found without ignoreMissing' => [
            '@Framework/documents/non_existing_template.html.twig',
            false,
            [],
            [],
            null,
            null,
        ];

        yield 'find correct template with source' => [
            '@Framework/documents/base.html.twig',
            false,
            [
                'Framework',
                'SwagTheme',
            ],
            $coreBundles,
            '@SwagTheme/documents/invoice.html.twig',
            '@SwagTheme/documents/base.html.twig',
        ];

        yield 'find correct template without source' => [
            '@Framework/documents/invoice.html.twig',
            false,
            [
                'SwagTheme',
                'Framework',
            ],
            $coreBundles,
            null,
            '@SwagTheme/documents/invoice.html.twig',
        ];

        // @SwagTheme/documents/base.html.twig is found even when the source is @Framework/documents/invoice.html.twig
        yield 'find correct template with same source with input template' => [
            '@Framework/documents/base.html.twig',
            false,
            [
                'Framework',
                'SwagTheme',
            ],
            $coreBundles,
            '@Framework/documents/invoice.html.twig',
            '@SwagTheme/documents/base.html.twig',
        ];

        yield 'return original template if template not found' => [
            '@Framework/documents/custom.html.twig',
            true,
            [
            ],
            $coreBundles,
            '@Framework/documents/invoice.html.twig',
            'documents/custom.html.twig',
        ];

        yield 'throw error if template not found' => [
            '@Framework/documents/custom.html.twig',
            false,
            [
            ],
            $coreBundles,
            '@Framework/documents/invoice.html.twig',
            null,
        ];
    }
}
