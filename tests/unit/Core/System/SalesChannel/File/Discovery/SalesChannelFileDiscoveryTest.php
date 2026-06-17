<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplatePathIteratorInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(SalesChannelFileDiscovery::class)]
class SalesChannelFileDiscoveryTest extends TestCase
{
    public function testItDiscoversSalesChannelFilesForFileFamily(): void
    {
        $discovery = new SalesChannelFileDiscovery(
            new SalesChannelFileStaticTemplateIterator([
                'files/agentic/llms.txt.twig',
                'files/agentic/custom.agent.twig',
                'files/seo/robots.txt.twig',
                'files/agentic/llms.txt.twig',
                'files/agentic/.well-known/ucp.json.twig',
                'files/agentic/not-loaded.txt.twig',
            ]),
            $this->createTemplateFinder([
                '@Framework/files/agentic/llms.txt.twig' => '',
                '@Framework/files/agentic/custom.agent.twig' => '',
                '@Framework/files/seo/robots.txt.twig' => '',
                '@Ucp/files/agentic/llms.txt.twig' => '',
                '@Ucp/files/agentic/.well-known/ucp.json.twig' => '',
            ]),
            new ArrayAdapter(),
        );

        $files = $discovery->discover('agentic');

        static::assertSame(['.well-known/ucp.json', 'custom.agent', 'llms.txt'], array_keys($files));
        static::assertSame('agentic', $files['llms.txt']->fileFamily);
        static::assertSame('files/agentic/llms.txt.twig', $files['llms.txt']->templatePath);
        static::assertSame('files/agentic/llms.txt.twig', $files['llms.txt']->baseTemplateName);
        static::assertSame('text/plain; charset=utf-8', $files['llms.txt']->contentType);
        static::assertSame(
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
            $files['llms.txt']->templates
        );
        static::assertSame('application/json; charset=utf-8', $files['.well-known/ucp.json']->contentType);
        static::assertSame('text/plain; charset=utf-8', $files['custom.agent']->contentType);
    }

    public function testItCanDiscoverAnotherFileFamily(): void
    {
        $discovery = new SalesChannelFileDiscovery(
            new SalesChannelFileStaticTemplateIterator([
                'files/agentic/llms.txt.twig',
                'files/seo/robots.txt.twig',
            ]),
            $this->createTemplateFinder([
                '@Framework/files/agentic/llms.txt.twig' => '',
                '@Framework/files/seo/robots.txt.twig' => '',
            ]),
            new ArrayAdapter(),
        );

        $files = $discovery->discover('seo');

        static::assertSame(['robots.txt'], array_keys($files));
        static::assertSame('seo', $files['robots.txt']->fileFamily);
        static::assertSame('files/seo/robots.txt.twig', $files['robots.txt']->templatePath);
    }

    public function testItCachesDiscoveredFileCatalogueAcrossInstances(): void
    {
        $cache = new ArrayAdapter();
        $templateFinder = $this->createTemplateFinder([
            '@Framework/files/agentic/llms.txt.twig' => '',
        ]);

        $firstTemplateIterator = $this->createMock(TemplatePathIteratorInterface::class);
        $firstTemplateIterator
            ->expects($this->once())
            ->method('getTemplatePathsForSubPath')
            ->with('files/agentic/', true)
            ->willReturn(['files/agentic/llms.txt.twig']);

        $secondTemplateIterator = $this->createMock(TemplatePathIteratorInterface::class);
        $secondTemplateIterator
            ->expects($this->never())
            ->method('getTemplatePathsForSubPath');

        $firstDiscovery = new SalesChannelFileDiscovery($firstTemplateIterator, $templateFinder, $cache);
        $secondDiscovery = new SalesChannelFileDiscovery($secondTemplateIterator, $templateFinder, $cache);

        static::assertArrayHasKey('llms.txt', $firstDiscovery->discover('agentic'));
        static::assertArrayHasKey('llms.txt', $secondDiscovery->discover('agentic'));
    }

    /**
     * @param array<string, string> $templates
     */
    private function createTemplateFinder(array $templates): TemplateFinder
    {
        $loader = new ArrayLoader($templates);
        $twig = new Environment($loader);

        return new TemplateFinder(
            $twig,
            $loader,
            '',
            new NamespaceHierarchyBuilder([
                new SalesChannelFileStaticHierarchyBuilder(['Framework' => -1, 'Ucp' => 0]),
            ]),
            new TemplateScopeDetector(new RequestStack()),
        );
    }
}

/**
 * @internal
 */
final readonly class SalesChannelFileStaticTemplateIterator implements TemplatePathIteratorInterface
{
    /**
     * @param list<string> $templatePaths
     */
    public function __construct(private array $templatePaths)
    {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->templatePaths;
    }

    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable
    {
        $subPath = trim($subPath, '/') . '/';

        foreach ($this->templatePaths as $templatePath) {
            if (!str_starts_with($templatePath, $subPath)) {
                continue;
            }

            if (!$includeDotFiles && str_contains('/' . mb_substr($templatePath, mb_strlen($subPath)), '/.')) {
                continue;
            }

            yield $templatePath;
        }
    }
}

/**
 * @internal
 */
final readonly class SalesChannelFileStaticHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @param array<string, int> $hierarchy
     */
    public function __construct(private array $hierarchy)
    {
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        return $this->hierarchy + $namespaceHierarchy;
    }
}
