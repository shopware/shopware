<?php declare(strict_types=1);

namespace Shopware\Framework\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ClassNamespaceTest extends TestCase
{
    public function test_all_production_files_are_namespaced_correctly()
    {
        $basePath = __DIR__ . '/../../';
        $basePathParts = explode('/', $basePath);

        $phpFiles = (new Finder())->files()->in($basePath)->name('*.php')->getIterator();

        $errors = [];
        foreach ($phpFiles as $file) {
            $parts = $this->extractProductionNamespaceParts($file, $basePathParts);

            $namespace = 'namespace Shopware\\' . implode('\\', $parts);

            if (false === strpos($file->getContents(), $namespace)) {
                $relativePath = str_replace($basePath, '', $file->getPathname());
                $errors['src' . $relativePath] = $namespace . ';';
            }
        }

        $errorMessage = 'Expected the following files to have a correct namespace:' . PHP_EOL . PHP_EOL . print_r($errors, true);

        self::assertCount(0, $errors, $errorMessage);
    }

    /**
     * @param SplFileInfo $file
     * @param string[] $basePathParts
     * @return string[]
     */
    private function extractProductionNamespaceParts(SplFileInfo $file, array $basePathParts): array
    {
        $parts = explode('/', (string) $file);
        $parts = array_slice($parts, count($basePathParts) - 1);
        $parts = array_filter($parts);

        array_pop($parts);

        return $parts;
    }
}
