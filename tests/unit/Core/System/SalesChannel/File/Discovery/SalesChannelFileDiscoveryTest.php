<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplatePathIteratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileDiscovery::class)]
class SalesChannelFileDiscoveryTest extends TestCase
{
    public function testItDiscoversSalesChannelFilesForFileFamily(): void
    {
        $discovery = new SalesChannelFileDiscovery(
            new SalesChannelFileStaticTemplateIterator([
                'files/agentic/llms.txt.twig',
                'files/agentic/AGENTS.md.twig',
                'files/agentic/agents.md.twig',
                'files/agentic/custom.agent.twig',
                'files/seo/robots.txt.twig',
                'files/agentic/llms.txt.twig',
                'files/agentic/.well-known/ucp.json.twig',
                'files/agentic/standalone.txt.twig',
            ]),
            $this->createCache(),
        );

        $files = $discovery->discover('agentic');

        static::assertSame(['.well-known/ucp.json', 'agents.md', 'custom.agent', 'llms.txt', 'standalone.txt'], array_keys($files));
        static::assertSame('AGENTS.md', $files['agents.md']->fileName);
        static::assertSame('files/agentic/AGENTS.md.twig', $files['agents.md']->templatePath);
        static::assertSame([
            'files/agentic/AGENTS.md.twig',
            'files/agentic/agents.md.twig',
        ], $files['agents.md']->templatePaths);
        static::assertSame('agentic', $files['llms.txt']->fileFamily);
        static::assertSame('files/agentic/llms.txt.twig', $files['llms.txt']->templatePath);
        static::assertSame('files/agentic/llms.txt.twig', $files['llms.txt']->baseTemplateName);
        static::assertSame('text/plain; charset=utf-8', $files['llms.txt']->contentType);
        static::assertSame([], $files['llms.txt']->templates);
        static::assertSame('application/json; charset=utf-8', $files['.well-known/ucp.json']->contentType);
        static::assertSame('text/plain; charset=utf-8', $files['custom.agent']->contentType);
        static::assertEquals($files['agents.md'], $discovery->get('files/agentic/AGENTS.md.twig'));
        static::assertEquals($files['agents.md'], $discovery->get('files/agentic/agents.md.twig'));
    }

    public function testItCanDiscoverAnotherFileFamily(): void
    {
        $discovery = new SalesChannelFileDiscovery(
            new SalesChannelFileStaticTemplateIterator([
                'files/agentic/llms.txt.twig',
                'files/seo/robots.txt.twig',
            ]),
            $this->createCache(),
        );

        $files = $discovery->discover('seo');

        static::assertSame(['robots.txt'], array_keys($files));
        static::assertSame('seo', $files['robots.txt']->fileFamily);
        static::assertSame('files/seo/robots.txt.twig', $files['robots.txt']->templatePath);
    }

    public function testItCachesDiscoveredFilesAcrossInstances(): void
    {
        $cache = $this->createCache();

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

        $firstDiscovery = new SalesChannelFileDiscovery($firstTemplateIterator, $cache);
        $secondDiscovery = new SalesChannelFileDiscovery($secondTemplateIterator, $cache);

        static::assertArrayHasKey('llms.txt', $firstDiscovery->discover('agentic'));
        static::assertSame([], $secondDiscovery->discover('agentic')['llms.txt']->templates);
    }

    private function createCache(): CacheInterface
    {
        return new TagAwareAdapter(new ArrayAdapter());
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
