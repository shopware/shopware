<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Framework\Mcp;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storefront;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Filesystem\Path;

/**
 * Guards the MCP discovery configuration in
 * src/Core/Framework/Resources/config/packages/mcp.php against a regression where
 * scan_dirs were hardcoded to the monorepo layout ("src/Core/Framework/Mcp"), which
 * silently registered zero tools on Composer/production installs (code under vendor/).
 *
 * @internal
 */
#[Package('framework')]
class McpDiscoveryScanDirsConfigTest extends TestCase
{
    /**
     * With an arbitrary project dir, every configured scan_dir must be relative and
     * resolve back to the bundle's real Mcp directory. A hardcoded "src/..." value
     * would resolve against the fake project dir and fail here, regardless of the
     * layout the test itself runs in.
     */
    public function testScanDirsResolveToBundleMcpDirectoriesForAnyProjectDir(): void
    {
        $projectDir = '/srv/some/unrelated/project-root';

        $scanDirs = $this->loadScanDirs($projectDir);

        static::assertNotEmpty($scanDirs);

        foreach ($scanDirs as $scanDir) {
            static::assertFalse(
                Path::isAbsolute($scanDir),
                'scan_dirs must be relative to project_dir; the MCP SDK joins basePath . "/" . dir.',
            );
        }

        $resolved = array_map(
            static fn (string $dir): string => Path::canonicalize($projectDir . '/' . $dir),
            $scanDirs,
        );

        static::assertContains($this->bundleMcpDir(Framework::class), $resolved);

        if (class_exists(Storefront::class)) {
            static::assertContains($this->bundleMcpDir(Storefront::class), $resolved);
        }
    }

    /**
     * Backwards compatibility: for a monorepo checkout (project dir = repo root) the
     * core scan dir stays "src/Core/Framework/Mcp".
     */
    public function testScanDirsMatchMonorepoLayoutWhenProjectDirIsRepoRoot(): void
    {
        $repoRoot = \dirname(__DIR__, 5);

        $scanDirs = $this->loadScanDirs($repoRoot);

        static::assertContains('src/Core/Framework/Mcp', $scanDirs);
    }

    /**
     * @return list<string>
     */
    private function loadScanDirs(string $projectDir): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        $container->setParameter('kernel.bundles_metadata', [
            'Framework' => ['path' => $this->bundleDir(Framework::class)],
            'Storefront' => ['path' => $this->bundleDir(Storefront::class)],
        ]);
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'mcp';
            }
        });

        $instanceof = [];
        $configurator = new ContainerConfigurator(
            $container,
            new PhpFileLoader($container, new FileLocator(__DIR__)),
            $instanceof,
            'mcp.php',
            'mcp.php',
        );

        $configure = require \dirname(__DIR__, 5) . '/src/Core/Framework/Resources/config/packages/mcp.php';
        $configure($configurator, $container);

        $configs = $container->getExtensionConfig('mcp');
        static::assertArrayHasKey(0, $configs);
        static::assertArrayHasKey('discovery', $configs[0]);

        /** @var list<string> $scanDirs */
        $scanDirs = $configs[0]['discovery']['scan_dirs'];

        return $scanDirs;
    }

    /**
     * @param class-string $bundleClass
     */
    private function bundleMcpDir(string $bundleClass): string
    {
        return Path::canonicalize($this->bundleDir($bundleClass) . '/Mcp');
    }

    /**
     * @param class-string $bundleClass
     */
    private function bundleDir(string $bundleClass): string
    {
        return \dirname((string) (new \ReflectionClass($bundleClass))->getFileName());
    }
}
