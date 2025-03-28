<?php
declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Extension\SnippetExtension;
use Shopware\Administration\Snippet\SnippetFilesFinderInterface;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class SnippetFinderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SnippetFilesFinderInterface&MockObject $snippetFilesFinder;

    private SnippetFinder $snippetFinder;

    protected function setUp(): void
    {
        $this->snippetFilesFinder = static::createMock(SnippetFilesFinderInterface::class);
        $this->snippetFinder = new SnippetFinder(
            static::getContainer()->get(Connection::class),
            $this->snippetFilesFinder,
            static::getContainer()->get(ExtensionDispatcher::class)
        );
    }

    public function testSnippetExtensionPre(): void
    {
        $locale = 'de-DE';
        $this->createAppSnippets($locale, ['app' => ['foo' => 'app.foo-default']]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = function (SnippetExtension $extension): void {
            $extension->snippets['test']['foo']['bar'] = 'foo.bar-extension';
            $extension->snippets['extension']['bar'] = 'extension.bar-extension';
        };

        $eventDispatcher->addListener(ExtensionDispatcher::pre(SnippetExtension::NAME), $listener);

        $this->snippetFilesFinder->method('findSnippetFiles')
            ->with($locale)
            ->willReturn([
                __DIR__ . '/fixtures/caseSnippetExtension/de-DE.json',
            ]);
        $actual = $this->snippetFinder->findSnippets($locale);

        $eventDispatcher->removeListener(ExtensionDispatcher::pre(SnippetExtension::NAME), $listener);

        static::assertEquals([
            'test' => [
                'foo' => [
                    'bar' => 'foo.bar-extension',
                    'baz' => 'foo.baz-default',
                ],
            ],
            'extension' => [
                'bar' => 'extension.bar-extension',
            ],
            'app' => [
                'foo' => 'app.foo-default',
            ],
        ], $actual);
    }

    public function _testSnippetExtensionPost(): void
    {
        $locale = 'de-DE';
        $this->createAppSnippets($locale, ['app' => ['foo' => 'app.foo-default']]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = function (SnippetExtension $extension): void {
            $extension->result['test']['foo']['bar'] = 'foo.bar-extension';
            $extension->result['extension']['bar'] = 'extension.bar-extension';
            $extension->result['app']['foo'] = 'app.foo-extension';
        };

        $eventDispatcher->addListener(ExtensionDispatcher::post(SnippetExtension::NAME), $listener);

        $this->snippetFilesFinder->method('findSnippetFiles')
            ->with($locale)
            ->willReturn([
                __DIR__ . '/fixtures/caseSnippetExtension/de-DE.json',
            ]);
        $actual = $this->snippetFinder->findSnippets($locale);

        $eventDispatcher->removeListener(ExtensionDispatcher::post(SnippetExtension::NAME), $listener);

        static::assertEquals([
            'test' => [
                'foo' => [
                    'bar' => 'foo.bar-extension',
                    'baz' => 'foo.baz-default',
                ],
            ],
            'extension' => [
                'bar' => 'extension.bar-extension',
            ],
            'app' => [
                'foo' => 'app.foo-extension',
            ],
        ], $actual);
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

    private function getLocalId(string $locale): ?string
    {
        $repository = static::getContainer()->get('locale.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('code', $locale))
            ->setLimit(1);

        return $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }

    /**
     * @param array<string, mixed> $snippets
     */
    private function createAppSnippets(
        string $locale,
        array $snippets
    ): void {
        $aclRoleId = Uuid::randomHex();
        $integrationId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        static::getContainer()->get('acl_role.repository')
            ->create([
                [
                    'id' => $aclRoleId,
                    'name' => 'foo',
                    'description' => '',
                    'privileges' => [],
                ],
            ], Context::createDefaultContext());

        static::getContainer()->get('integration.repository')
            ->create([
                [
                    'id' => $integrationId,
                    'label' => 'foo',
                    'accessKey' => 'accessKey',
                    'secretAccessKey' => 'secretAccessKey',
                ],
            ], Context::createDefaultContext());

        static::getContainer()->get('app.repository')
            ->create([
                [
                    'id' => $appId,
                    'integrationId' => $integrationId,
                    'aclRoleId' => $aclRoleId,
                    'active' => true,
                    'version' => '1.2.3',
                    'name' => 'test',
                    'translations' => [
                        Defaults::LANGUAGE_SYSTEM => [
                            'label' => 'Foo',
                        ],
                    ],
                    'path' => __DIR__ . '/fixtures/caseSnippetExtension',
                    'author' => 'test',
                ],
            ], Context::createDefaultContext());

        static::getContainer()->get('app_administration_snippet.repository')
            ->create([
                [
                    'id' => Uuid::randomHex(),
                    'appId' => $appId,
                    'localeId' => $this->getLocalId($locale),
                    'value' => json_encode($snippets),
                ],
            ], Context::createDefaultContext());
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
