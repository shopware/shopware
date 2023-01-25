<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Twig\TemplateFinder
 */
class TemplateFinderTest extends TestCase
{
    /**
     * @var NamespaceHierarchyBuilder&MockObject
     */
    private NamespaceHierarchyBuilder $hierarchyBuilder;

    /**
     * @var LoaderInterface&MockObject
     */
    private LoaderInterface $loader;

    private TemplateFinder $finder;

    protected function setUp(): void
    {
        $this->hierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $this->loader = $this->createMock(LoaderInterface::class);
        $twig = $this->createMock(Environment::class);
        $this->finder = new TemplateFinder($twig, $this->loader, '', $this->hierarchyBuilder);
    }

    /**
     * @dataProvider templateNameProvider
     */
    public function testGetTemplateName(string $input, string $expectation): void
    {
        static::assertEquals($expectation, $this->finder->getTemplateName($input));
    }

    /**
     * @dataProvider bundleTemplatesMappingProvider
     *
     * @param array<int, string> $templateExists
     * @param array<string, bool> $bundles
     */
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

    /**
     * @return iterable<string, array<int, string>>
     */
    public function templateNameProvider(): iterable
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
    public function bundleTemplatesMappingProvider(): iterable
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
