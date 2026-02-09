<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Snippet;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPath;
use Shopware\Core\System\Snippet\DataTransfer\SnippetPath\SnippetPathCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Tests\Unit\Core\System\Snippet\Service\TestableTranslationConfigLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetFinder::class)]
class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SnippetFinder $snippetFinder;

    protected function setUp(): void
    {
        $flySystem = new Flysystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);
        $configLoader = new TestableTranslationConfigLoader(new Filesystem());
        $configLoader->setRelativeConfigurationPath(__DIR__ . '/fixtures/translationConfig');

        $this->snippetFinder = new SnippetFinder(
            self::getKernel(),
            static::getContainer()->get(Connection::class),
            $flySystem,
            $configLoader->load(),
            static::getContainer()->get(TranslationLoader::class),
            static::getContainer()->get(HtmlSanitizer::class),
        );
    }

    public function testValidSnippetMergeWithOnlySameLanguageFiles(): void
    {
        $actual = $this->getResultSnippetsByCase('caseSameLanguage', 'de');

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
        $actual = $this->getResultSnippetsByCase('caseDifferentLanguages', 'de');

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

        static::assertSame($expected, $actual);
    }

    public function testValidSnippetMergeWithMultipleLanguageFiles(): void
    {
        $actualDe = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'de');
        $actualEn = $this->getResultSnippetsByCase('caseMultipleSameAndDifferentLanguages', 'en');

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

    public function testSnippetFinderSanitizesAppSnippets(): void
    {
        $this->createAppWithMalformedSnippet();
        $snippets = $this->snippetFinder->findSnippets('en-GB');

        $actualSnippet = $snippets['theme']['label'];
        static::assertSame('<h1>This app</h1> is really <b>safe</b>!)', $actualSnippet);
    }

    private function createAppWithMalformedSnippet(): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('app.repository')->create([
            [
                'id' => $id = Uuid::randomHex(),
                'name' => 'Test app',
                'active' => true,
                'appVersion' => '1.0.0',
                'author' => 'Shopware AG',
                'label' => [
                    'en-GB' => 'Test App',
                ],
                'path' => 'path',
                'version' => '1.0.0',
                'integration' => [
                    'id' => Uuid::randomHex(),
                    'label' => 'Test app Integration',
                    'accessKey' => Uuid::randomHex(),
                    'secretAccessKey' => Uuid::randomHex(),
                ],
                'aclRole' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'Test app ACL Role',
                ],
            ],
        ], $context);

        static::getContainer()->get('app_administration_snippet.repository')->create([
            [
                'appId' => $id,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'value' => '{"theme":{"label":"<script>alert(\"xss attack\");</script><h1>This app</h1> is really <b>safe</b>!)"}}',
            ],
        ], $context);
    }

    private function getSnippetFilePathsOfFixtures(string $folder, string $namePattern): SnippetPathCollection
    {
        $finder = (new Finder())
            ->files()
            ->in(__DIR__ . '/fixtures/' . $folder . '/')
            ->ignoreUnreadableDirs()
            ->name($namePattern);

        $fileArray = array_map(fn (SplFileInfo $file) => $file->getRealPath(), \iterator_to_array($finder->getIterator()));
        $fileArray = $this->ensureFileOrder(\array_values($fileArray));

        $files = new SnippetPathCollection();
        foreach ($fileArray as $file) {
            $files->add(new SnippetPath($file, true));
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function getResultSnippetsByCase(string $folder, string $localeInFilename): array
    {
        $files = $this->getSnippetFilePathsOfFixtures($folder, '/' . $localeInFilename . '.json/');

        $reflectionClass = new \ReflectionClass(SnippetFinder::class);
        $reflectionMethod = $reflectionClass->getMethod('parseFiles');

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
