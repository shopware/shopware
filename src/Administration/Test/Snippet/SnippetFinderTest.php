<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Snippet;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Finder\Finder;

class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SnippetFinder
     */
    private $snippetFinder;

    protected function setUp(): void
    {
        $this->snippetFinder = new SnippetFinder($this->getKernel());
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

    private function ensureFileOrder(array $files): array
    {
        // core should be overwritten by plugin fixture, therefore core should be index 0
        if (mb_strpos($files[0], '/core/') === false) {
            foreach ($files as $currentIndex => $file) {
                if (mb_strpos($file, '/core/') !== false) {
                    [$files[0], $files[$currentIndex]] = [$files[$currentIndex], $files[0]];

                    return $files;
                }
            }
        }

        return $files;
    }
}
