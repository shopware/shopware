<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Finder\Finder;

class DeprecatedTagTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * white list file path segments for ignored paths
     *
     * @var array
     */
    private $whiteList = [
        'Test/',
    ];

    public function testAllPhpFilesInPlatformForDeprecated(): void
    {
        $dir = $this->getContainer()->getParameter('kernel.project_dir') . '/platform';

        $return = [];
        $finder = new Finder();
        $finder->in($dir)
            ->files()
            ->name('*.php')
            ->contains('@deprecated');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        foreach ($finder->getIterator() as $file) {
            if ($this->hasDeprecatedMethodWithFalseOrNoTag($file->getPathname())) {
                $return[] = $file->getPathname();
            }
        }

        static::assertEquals([], $return, print_r($return, true));
    }

    private function hasDeprecatedMethodWithFalseOrNoTag(string $file): bool
    {
        $content = file_get_contents($file);
        $matches = [];
        $pattern = '/@deprecated(?!\stag\:)/';
        preg_match($pattern, $content, $matches);

        if (!empty(array_filter($matches))) {
            return true;
        }

        $pattern = '/@deprecated\stag\:v{1}([0-9,\.]{2,5})/';
        preg_match_all($pattern, $content, $matches);

        $matches = $matches[1];

        if (empty(array_filter($matches))) {
            return true;
        }

        $taggedVersion = $this->getTaggedVersion();

        foreach ($matches as $match) {
            if (version_compare($taggedVersion, $match) !== -1) {
                return true;
            }
        }

        return false;
    }

    /**
     * can be overwritten with env variable VERSION
     */
    private function getTaggedVersion(): string
    {
        $envVersion = $_SERVER['VERSION'] ?? '';
        if (is_string($envVersion) && $envVersion !== '') {
            return $envVersion;
        }

        return str_replace('v', '', $this->exec('git describe --tags $(git rev-list --tags --max-count=1)'));
    }

    private function exec(string $command): string
    {
        $result = [];
        $exitCode = 0;

        exec($command, $result, $exitCode);

        if ($exitCode !== 0) {
            throw new \Exception("Could not execute {$command} successfully. EXITING \n");
        }

        return $result[0];
    }
}
