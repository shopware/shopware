<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for routes.xml. Every UCP controller that carries
 * a #[Route] attribute must be picked up by one of the <import> globs
 * in `src/Core/Framework/Resources/config/routes.xml`. Without an
 * import the controller's routes never reach Symfony's router and
 * the endpoint silently returns 404 — even though the DI service
 * is wired and reachable via `Container::get()`. This happened once
 * (see commit history) for the A2A, Embedded and Tokenization
 * transports; this test prevents the same regression for any new
 * sub-namespace under `Core/Framework/Ucp/`.
 *
 * @internal
 */
#[CoversNothing]
class UcpRoutesRegistrationTest extends TestCase
{
    private const CORE_ROOT = __DIR__ . '/../../../../../src/Core';

    public function testEveryRoutedUcpControllerIsCoveredByARouteImport(): void
    {
        $routesXmlPath = self::CORE_ROOT . '/Framework/Resources/config/routes.xml';
        static::assertFileExists($routesXmlPath, 'routes.xml is required for this test.');

        $xml = file_get_contents($routesXmlPath);
        static::assertIsString($xml);

        $importPatterns = $this->extractUcpImportPatterns($xml);
        static::assertNotEmpty(
            $importPatterns,
            'routes.xml must contain at least one <import resource="../../Ucp/.../*Controller.php"/> entry.'
        );

        $uncovered = [];
        foreach ($this->findRoutedUcpControllers() as $relativePath) {
            $matched = false;
            foreach ($importPatterns as $pattern) {
                if ($this->matchesGlob($pattern, $relativePath)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $uncovered[] = $relativePath;
            }
        }

        static::assertSame(
            [],
            $uncovered,
            \sprintf(
                'The following UCP controllers carry a #[Route] attribute but are not covered by any '
                . '<import resource="../../Ucp/.../*Controller.php"/> in routes.xml, '
                . "so Symfony's router will silently ignore them:\n  - %s",
                implode("\n  - ", $uncovered)
            )
        );
    }

    /**
     * @return list<string> e.g. ["Ucp/Transport/**\/*Controller.php", "Ucp/Capability/**\/*Controller.php"]
     */
    private function extractUcpImportPatterns(string $xml): array
    {
        if (!preg_match_all(
            '#<import\s+resource="\.\./\.\./(Ucp/[^"]+\.php)"#',
            $xml,
            $matches
        )) {
            return [];
        }

        return array_values($matches[1]);
    }

    /**
     * @return list<string> paths relative to `src/Core/Framework/`, e.g. "Ucp/Transport/A2A/A2AController.php"
     */
    private function findRoutedUcpControllers(): array
    {
        $ucpRoot = self::CORE_ROOT . '/Framework/Ucp';
        if (!is_dir($ucpRoot)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ucpRoot, \FilesystemIterator::SKIP_DOTS)
        );

        $controllers = [];
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (!str_ends_with($fileInfo->getFilename(), 'Controller.php')) {
                continue;
            }

            $contents = file_get_contents($fileInfo->getPathname());
            if (!\is_string($contents) || !str_contains($contents, '#[Route')) {
                continue;
            }

            $relative = substr($fileInfo->getPathname(), \strlen(self::CORE_ROOT . '/Framework/'));
            $controllers[] = str_replace('\\', '/', $relative);
        }

        sort($controllers);

        return $controllers;
    }

    /**
     * Mirrors the subset of Symfony's directory glob semantics used in routes.xml:
     *   `**` matches one or more path segments (or zero segments, with the trailing slash collapsed)
     *   `*`  matches one path segment (any character except `/`)
     *   everything else is matched literally.
     */
    private function matchesGlob(string $pattern, string $path): bool
    {
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('/\*\*/', '(?:/.+/|/)', $regex);
        $regex = str_replace('\*\*', '.*', $regex);
        $regex = str_replace('\*', '[^/]*', $regex);

        return (bool) preg_match('#^' . $regex . '$#', $path);
    }
}
