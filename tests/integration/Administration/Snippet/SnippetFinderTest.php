<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SnippetFinder $snippetFinder;

    protected function setUp(): void
    {
        $this->snippetFinder = new SnippetFinder(
            self::getKernel(),
            $this->getContainer()->get(Connection::class)
        );
    }

    public function testValidSnippetMergeWithOnlySameLanguageFiles(): void
    {
        $actual = $this->getResultSnippetsByCase('caseSameLanguage', 'de-DE');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin',
                    'anotherLabel' => 'plugin',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'uniqueKeyPlugin' => 'plugin',
                    'shouldBeOverwritten' => 'overwritten by plugin',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin',
                ],
            ],
        ];

        static::assertEquals($expected, $actual);
    }

    public function testValidSnippetMergeWithDifferentLanguageFiles(): void
    {
        $actual = $this->getResultSnippetsByCase('caseDifferentLanguages', 'de-DE');

        $expected = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core',
                    'anotherLabel' => 'core',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core',
                    'shouldBeOverwritten' => 'This time no override',
                    'shouldAlsoBeOverwritten' => 'This time no override either',
                ],
            ],
        ];

        static::assertEquals($expected, $actual);
    }

    public function testValidSnippetMergeWithMultipleLanguageFiles(): void
    {
        $actualDe = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'de-DE');
        $actualEn = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'en-GB');

        $expectedDe = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core de',
                    'anotherLabel' => 'core de',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin de',
                    'anotherLabel' => 'plugin de',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core de',
                    'uniqueKeyPlugin' => 'plugin de',
                    'shouldBeOverwritten' => 'overwritten by plugin de',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin de',
                ],
            ],
        ];

        $expectedEn = [
            'test' => [
                'uniqueNamespaceCore' => [
                    'someLabel' => 'core en',
                    'anotherLabel' => 'core en',
                ],
                'uniqueNamespacePlugin' => [
                    'someLabel' => 'plugin en',
                    'anotherLabel' => 'plugin en',
                ],
                'someSharedNamespace' => [
                    'uniqueKeyCore' => 'core en',
                    'uniqueKeyPlugin' => 'plugin en',
                    'shouldBeOverwritten' => 'overwritten by plugin en',
                    'shouldAlsoBeOverwritten' => 'also overwritten by plugin en',
                ],
            ],
        ];

        static::assertEquals($expectedDe, $actualDe);
        static::assertEquals($expectedEn, $actualEn);
    }

    /**
     * @return array<string>
     */
    private function getSnippetFilePathsOfFixtures(string $folder, string $namePattern): array
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/fixtures/' . $folder . '/')
            ->ignoreUnreadableDirs()
            ->name($namePattern);

        $iterator = $finder->getIterator();

        $files = [];
        foreach ($iterator as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function getResultSnippetsByCase(string $folder, string $locale): array
    {
        $files = $this->getSnippetFilePathsOfFixtures($folder, '/' . $locale . '.json/');
        $files = $this->ensureFileOrder($files);

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('parseFiles');
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke(
            $this->snippetFinder,
            $files
        );
    }

    /**
     * @param array<int, string> $files
     *
     * @return array<int, string>
     */
    private function ensureFileOrder(array $files): array
    {
        // core should be overwritten by plugin fixture, therefore core should be index 0
        if (!str_contains($files[0], '/core/')) {
            foreach ($files as $currentIndex => $file) {
                if (str_contains($file, '/core/')) {
                    [$files[0], $files[$currentIndex]] = [$files[$currentIndex], $files[0]];

                    return $files;
                }
            }
        }

        return $files;
    }
}

/**
 * @internal
 */
class FakePlugin extends Plugin
{
    public function getPath(): string
    {
        return __DIR__ . '/fixtures/caseBundleLoadingWithPlugin/bundle';
    }
}
