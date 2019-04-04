<?php declare(strict_types=1);

namespace Shopware\Docs\Test\Command;

use GuzzleHttp\Exception\ClientException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Docs\Command\SyncDocsCommand;

class SyncDocsCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateAndDeleteCategory(): void
    {
        $commandTester = new SyncDocsCommand();

        $categoryId = $commandTester->createCategory(
            'Test',
            '/testTest',
            'Testsubjekt',
            '/testSeo'
        );

        $category = $this->getCategoryContents($commandTester, $categoryId);

        static::assertCount(2, $category['localizations']);
        static::assertEquals($commandTester->getRootCategoryId(), $category['parent']['id']);

        foreach ($category['localizations'] as $localizations) {
            if ($localizations['locale']['name'] === 'en_GB') {
                static::assertEquals('Test', $localizations['title']);
                static::assertEquals('/testTest', $localizations['seoUrl']);
            } elseif ($localizations['locale']['name'] === 'de_DE') {
                static::assertEquals('Testsubjekt', $localizations['title']);
                static::assertEquals('/testSeo', $localizations['seoUrl']);
            }
            static::assertFalse($localizations['searchableInAllLanguages']);
            static::assertEquals('', $localizations['content']);
        }

        $commandTester->deleteCategory($categoryId);

        $this->expectExceptionMessage('category not found');
        $this->getCategoryContents($commandTester, $categoryId);
    }

    public function testCreateAndDeleteArticle(): void
    {
        $commandTester = new SyncDocsCommand();

        $articleInfos = $commandTester->createLocalizedVersionedArticle(
            '/testingArticles', '/testingSehrGuteArtikel'
        );

        static::assertCount(2, $articleInfos);
        static::assertArrayHasKey('de_DE', $articleInfos);
        static::assertArrayHasKey('en_GB', $articleInfos);

        foreach ($articleInfos as $lang => $articleInfo) {
            static::assertArrayHasKey('articleId', $articleInfo);
            static::assertArrayHasKey('localeId', $articleInfo);
            static::assertArrayHasKey('versionId', $articleInfo);

            $contents = $commandTester->getLocalizedVersionedArticle($articleInfo);
            static::assertFalse($contents['active']);
            static::assertEquals('', $contents['content']);
            static::assertEquals('', $contents['title']);
        }

        $article = $commandTester->getArticle($articleInfos['en_GB']['articleId']);
        static::assertEmpty($article['categories']);
        static::assertNull($article['orderPriority']);
        // todo: test seo url

        $commandTester->deleteArticle($articleInfos['en_GB']['articleId']);

        $this->expectExceptionCode(404);
        $commandTester->getArticle($articleInfos['en_GB']['articleId']);
    }

    /**
     * @depends testCreateAndDeleteArticle
     */
    public function testUpdateArticleContents(): void
    {
        $commandTester = new SyncDocsCommand();

        $articleInfos = $commandTester->createLocalizedVersionedArticle(
            '/testingArticles', '/testingSehrGuteArtikel'
        );
        static::assertArrayHasKey('en_GB', $articleInfos);
        $articleInfo = $articleInfos['en_GB'];

        $expectedContents = '<p>Hello World!</p>';
        $commandTester->updateArticleVersion($articleInfo,
            ['content' => $expectedContents]
        );

        static::assertEquals($expectedContents,
            $commandTester->getLocalizedVersionedArticle($articleInfo)['content']
        );

        $expectedTitle = 'Best Article 2019';
        $commandTester->updateArticleVersion($articleInfo,
            ['title' => $expectedTitle]
        );

        $actual = $commandTester->getLocalizedVersionedArticle($articleInfo);
        static::assertEquals($expectedContents, $actual['content']);
        static::assertEquals($expectedTitle, $actual['title']);

        $commandTester->deleteArticle($articleInfo['articleId']);
    }

    /**
     * @depends testUpdateArticleContents
     */
    public function testEnableDisableArticle(): void
    {
        $commandTester = new SyncDocsCommand();

        $articleInfos = $commandTester->createLocalizedVersionedArticle(
            '/testingArticles', '/testingSehrGuteArtikel'
        );
        static::assertArrayHasKey('en_GB', $articleInfos);
        $articleInfo = $articleInfos['en_GB'];

        // these are the minimum known requirements to be able to activate an article
        $commandTester->updateArticleVersion($articleInfo,
            [
                'content' => '<p>Hello World!</p>',
                'title' => 'Best Article 2019',
                'navigationTitle' => 'Best Article 2019',
                'fromProductVersion' => SyncDocsCommand::INITIAL_VERSION,
            ]
        );

        $commandTester->updateArticleVersion($articleInfo, ['active' => true]);

        static::assertTrue($commandTester->getLocalizedVersionedArticle($articleInfo)['active']);

        $didCatch = false;
        // if we try to delete an active article, we should get an error
        try {
            $commandTester->deleteArticle($articleInfo['articleId']);
        } catch (ClientException $e) {
            if ($e->getCode() === 400) {
                $didCatch = true;
            }
        }
        static::assertTrue($didCatch);

        $commandTester->disableArticle($articleInfo['articleId']);
        $commandTester->deleteArticle($articleInfo['articleId']);
    }

    public function testDeleteCategoryTree(): void
    {
        static::markTestIncomplete('missing permissions');
    }

    public function testUpdateCategoryPage(): void
    {
        static::markTestIncomplete('missing permissions');
    }

    public function getCategoryContents($commandTester, $categoryId)
    {
        $allCategories = $commandTester->getAllCategories();
        $allCategoryIds = array_column($allCategories, 'id');

        if (!in_array($categoryId, $allCategoryIds, true)) {
            throw new \RuntimeException('category not found');
        }

        return $allCategories[array_search($categoryId, $allCategoryIds, true)];
    }

    public function testCreateAndFetchArticleTreeEqual(): void
    {
        $commandTester = new SyncDocsCommand();

        $createdTree = [];
        $commandTester->getOrCreateMissingCategoryTree(['a', 'b1', 'c'], $createdTree);
        $commandTester->getOrCreateMissingCategoryTree(['a', 'b2'], $createdTree);
        $invertedTree = array_combine(array_values($createdTree), array_keys($createdTree));

        [$categoriesFetched, $articlesFetched] = $commandTester->gatherCategoryChildrenAndArticles(
            $invertedTree['/a'],
            $commandTester->getAllCategories()
        );

        // usually, we would gather all children in the root Id. But we cannot clear the whole tree on the server
        // in every test run
        $categoriesFetched[$invertedTree['/a']] = '/a';

        $commandTester->deleteCategoryChildren($invertedTree['/a']);
        $commandTester->deleteCategory($invertedTree['/a']);

        static::assertEquals($createdTree, $categoriesFetched);
    }

    public function testSyncProcess(): void
    {
        static::markTestIncomplete();
        $commandTester = new SyncDocsCommand();

        $testCatName = 'UnitTestCat';
        $testCatId = $commandTester->createCategory($testCatName,
            $testCatName,
            $testCatName . 'de',
            $testCatName . 'de'
        );

        $commandTester->syncFilesWithServer([
            '/' . $testCatName . '/file.html' => [
                'content' => '<p>My awesome content !!!</p>',
                'metadata' => [
                    'titleDe' => 'Hallo',
                    'titleEn' => 'Hello',
                ],
            ],
            '/' . $testCatName . '/secondfile.html' => [
                'content' => '<p>My awesome second content !!!</p>',
                'metadata' => [
                    'titleDe' => 'Welt',
                    'titleEn' => 'World',
                ],
            ],
        ]);

        $commandTester->deleteCategoryChildren($testCatId);
        $commandTester->deleteCategory($testCatId);

        static::assertTrue(true);
    }

    /**
     * @depends testSyncProcess
     */
    public function testCategorySiteFiles(): void
    {
        static::markTestIncomplete();
        $commandTester = new SyncDocsCommand();

        //todo: ändern/anmerken: Categorieseiten mit inhalt ohne children nicht mgl

        $commandTester->syncFilesWithServer([
            './my/awesome/long/path/to/the/other/file.html' => [
                'content' => '<p>My awesome content !!!</p>',
                'metadata' => [
                    'titleDe' => 'Hallo2',
                    'titleEn' => 'Hello2',
                ],
            ],
            './my/awesome/long/path/to/the/' . SyncDocsCommand::categorysiteFilename . '.html' => [
                'content' => '<p>My category content !!!</p>',
                'metadata' => [
                    'titleEn' => 'Awesome Category',
                    'titleDe' => 'Sehr gute kategorie',
                ],
            ],
        ]);

        static::assertTrue(true);
    }
}
