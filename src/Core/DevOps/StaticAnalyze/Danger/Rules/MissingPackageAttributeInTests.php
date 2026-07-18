<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Every test class must carry `#[Package('…')]` so failing CI jobs (especially the
 * nightlies) can be routed to the owning domain team without guessing.
 *
 * The rule also suggests the value: for tests carrying `#[CoversClass]` it reads the
 * covered class's package; otherwise it falls back to the dominant package marker of
 * the `src/` subtree mirrored by the test's path.
 *
 * @internal
 */
#[Package('framework')]
class MissingPackageAttributeInTests
{
    public function __construct(private readonly string $projectDir = __DIR__ . '/../../../../../..')
    {
    }

    public function __invoke(Context $context): void
    {
        $addedFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED);

        $violations = [];

        foreach ($addedFiles->matches('tests/**/*Test.php') as $file) {
            $content = $file->getContent();

            if (!preg_match('/\bclass\s+\w+Test\s+extends\b/', $content)) {
                continue;
            }

            if (str_contains($content, '#[Package(')) {
                continue;
            }

            $suggestion = $this->suggestPackage($file->name, $content);

            $violations[] = $suggestion === null
                ? \sprintf('`%s`', $file->name)
                : \sprintf('`%s` — probably `#[Package(\'%s\')]`', $file->name, $suggestion);
        }

        if ($violations !== []) {
            $context->failure(
                'Every test class needs the `#[Package(\'…\')]` attribute (same value as the covered domain), '
                . 'so failing CI jobs can be routed to the owning team:<br/>'
                . implode('<br/>', $violations)
            );
        }
    }

    private function suggestPackage(string $testPath, string $content): ?string
    {
        return $this->packageOfCoveredClass($content) ?? $this->packageOfMirroredSrcDirectory($testPath);
    }

    /**
     * Resolves `#[CoversClass(X::class)]` through the file's use statements and reads
     * the covered class's `#[Package]` value.
     */
    private function packageOfCoveredClass(string $content): ?string
    {
        if (!preg_match('/#\[CoversClass\(\\\\?([\w\\\\]+)::class\)\]/', $content, $covers)) {
            return null;
        }

        $coveredClass = $covers[1];

        if (!str_contains($coveredClass, '\\') && preg_match('/^use\s+([\w\\\\]+\\\\' . $coveredClass . ');$/m', $content, $use)) {
            $coveredClass = $use[1];
        }

        $srcFile = $this->classToSrcFile($coveredClass);
        if ($srcFile === null || !is_file($srcFile)) {
            return null;
        }

        return $this->extractPackage((string) file_get_contents($srcFile));
    }

    /**
     * Maps e.g. `tests/integration/Core/Checkout/Cart/…Test.php` to `src/Core/Checkout/Cart`
     * (walking up until the directory exists) and returns the subtree's dominant package.
     */
    private function packageOfMirroredSrcDirectory(string $testPath): ?string
    {
        $mirrored = preg_replace('#^tests/(unit|integration|migration|devops)/#', 'src/', \dirname($testPath));
        if ($mirrored === null || !str_starts_with($mirrored, 'src/')) {
            return null;
        }

        while ($mirrored !== 'src' && !is_dir($this->projectDir . '/' . $mirrored)) {
            $mirrored = \dirname($mirrored);
        }

        if ($mirrored === 'src') {
            return null;
        }

        $votes = [];
        foreach ((array) glob($this->projectDir . '/' . $mirrored . '/*.php') as $srcFile) {
            $package = $this->extractPackage((string) file_get_contents((string) $srcFile));
            if ($package !== null) {
                ++$votes[$package];
            }
        }

        if ($votes === []) {
            return null;
        }

        arsort($votes);

        return (string) array_key_first($votes);
    }

    private function classToSrcFile(string $class): ?string
    {
        if (!str_starts_with($class, 'Shopware\\')) {
            return null;
        }

        $relative = str_replace('\\', '/', substr($class, \strlen('Shopware\\')));

        return $this->projectDir . '/src/' . $relative . '.php';
    }

    private function extractPackage(string $content): ?string
    {
        if (preg_match('/#\[Package\(\'([\w@-]+)\'\)\]/', $content, $package)) {
            return $package[1];
        }

        return null;
    }
}
