<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Snippet\SnippetService;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\Snippet\_fixtures\SnippetFileMock;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testEmptyList\EmptySnippetFile;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetList\SnippetFile_bar_bar;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetList\SnippetFile_foo_foo;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT_e2;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US_e2;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets\SnippetFile_de;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets\SnippetFile_en;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetServiceTest extends TestCase
{
    use IntegrationTestBehaviour,
        AssertArraySubsetBehaviour;

    /**
     * @dataProvider dataProviderForTestGetStoreFrontSnippets
     */
    public function testGetStoreFrontSnippets(MessageCatalogueInterface $catalog, array $expectedResult): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new SnippetFile_de());
        $collection->add(new SnippetFile_en());

        $service = new SnippetService(
            $this->getContainer()->get(Connection::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository'),
            $this->getContainer()->get(SnippetFilterFactory::class)
        );

        $result = $service->getStorefrontSnippets($catalog, Defaults::SNIPPET_BASE_SET_EN);

        static::assertSame($expectedResult, $result);
    }

    public function dataProviderForTestGetStoreFrontSnippets(): array
    {
        return [
            [$this->getCatalog([], 'en_GB'), []],
            [$this->getCatalog(['messages' => ['a' => 'a']], 'en_GB'), ['a' => 'a']],
            [$this->getCatalog(['messages' => ['a' => 'a', 'b' => 'b']], 'en_GB'), ['a' => 'a', 'b' => 'b']],
        ];
    }

    public function testGetLocaleBySnippetSetId(): void
    {
        $service = $this->getSnippetService();

        $method = ReflectionHelper::getMethod(SnippetService::class, 'getLocaleBySnippetSetId');
        $result_en_GB = $method->invoke($service, Defaults::SNIPPET_BASE_SET_EN);
        $result_de_DE = $method->invoke($service, Defaults::SNIPPET_BASE_SET_DE);

        static::assertSame(Defaults::LOCALE_EN_GB_ISO, $result_en_GB);
        static::assertSame(Defaults::LOCALE_DE_DE_ISO, $result_de_DE);
    }

    public function testGetDefaultLocaleExpectEnGB(): void
    {
        $method = ReflectionHelper::getMethod(SnippetService::class, 'getDefaultLocale');
        $result = $method->invoke($this->getSnippetService());

        static::assertSame(Defaults::LOCALE_EN_GB_ISO, $result);
    }

    public function testFillBlankSnippets(): void
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'fillBlankSnippets');

        $isoList = ['unit_TEST' => 'unit_TEST', 'en_GB' => 'en_GB'];

        $expectedResult = $snippetList = [
            'unit_TEST' => [
                'snippets' => [
                    'required.unit.test.snippet' => 'This snippet is missing in the other language and needs to be filled up',
                ],
            ],
            'en_GB' => [
                'snippets' => [
                    'required.unit.test.snippet' => '',
                ],
            ],
        ];

        $result = $mehtod->invokeArgs($service, [$snippetList, $isoList]);

        static::assertSame($expectedResult, $result);
    }

    public function testFetchSnippetsFromDatabase(): void
    {
        $sql = file_get_contents(__DIR__ . '/_fixtures/snippets-for-searching.sql');
        $this->getContainer()->get(Connection::class)->executeQuery($sql);

        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'fetchSnippetsFromDatabase');

        $expextedResult = [
            'detail.buyAddButton' => 'This is a test string',
            'detail.configSubmit' => 'A new test string',
            'detail.descriptionHeader' => 'Just another test string',
            'documents.index_ls.DocumentIndexInvoiceID' => 'Tangled',
            'documents.index.DocumentIndexHeadNet' => 'their dogs were astronauts',
            'documents.index.DocumentIndexHeadNetAmount' => 'The mystery science theater 3000',
            'documents.index.DocumentIndexHeadPosition' => 'Coincidence',
            'footer.copyright' => 'Only possible with unit tests',
            'footer.navigation1' => 'Who is Batman',
            'footer.navigation2' => 'Maps of non existent Places',
            'footer.newsletter' => 'Thank you scientist',
        ];

        $result = $mehtod->invoke($service, Defaults::SNIPPET_BASE_SET_EN);

        static::assertSame($expextedResult, $result);
    }

    /**
     * @dataProvider DataProviderForTestMergeSnippetsComparison
     */
    public function testMergeSnippetsComparison(array $sets, $expectedResult): void
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'mergeSnippetsComparison');

        $result = $mehtod->invoke($service, $sets);

        $this->silentAssertArraySubset($expectedResult, $result);
    }

    public function DataProviderForTestMergeSnippetsComparison(): array
    {
        $parameter = require __DIR__ . '/_fixtures/SnippetComparison.php';

        return [
            [[], []],
            [$parameter['set1'], $parameter['result1']],
            [$parameter['set2'], $parameter['result2']],
        ];
    }

    public function testGetSnippetsFromFiles(): void
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getSnippetsFromFiles');

        $snippetFileMock = new SnippetFileMock();

        $expectedResult = [
            'only.possible.with.unitTests.test1' => ['value' => 'this is test 1.', 'origin' => 'this is test 1.', 'translationKey' => 'only.possible.with.unitTests.test1', 'setId' => 'setId'],
            'only.possible.with.unitTests.test2' => ['value' => 'this is test 2.', 'origin' => 'this is test 2.', 'translationKey' => 'only.possible.with.unitTests.test2', 'setId' => 'setId'],
            'only.possible.with.unitTests.test3' => ['value' => 'this is test 3.', 'origin' => 'this is test 3.', 'translationKey' => 'only.possible.with.unitTests.test3', 'setId' => 'setId'],
            'only.possible.with.unitTests.test4' => ['value' => 'this is test 4.', 'origin' => 'this is test 4.', 'translationKey' => 'only.possible.with.unitTests.test4', 'setId' => 'setId'],
            'only.possible.with.unitTests.test5' => ['value' => 'this is test 5.', 'origin' => 'this is test 5.', 'translationKey' => 'only.possible.with.unitTests.test5', 'setId' => 'setId'],
            'only.possible.with.unitTests.test6' => ['value' => 'this is test 6.', 'origin' => 'this is test 6.', 'translationKey' => 'only.possible.with.unitTests.test6', 'setId' => 'setId'],
            'only.possible.with.unitTests.test7' => ['value' => 'this is test 7.', 'origin' => 'this is test 7.', 'translationKey' => 'only.possible.with.unitTests.test7', 'setId' => 'setId'],
            'only.possible.with.unitTests.test8' => ['value' => 'this is test 8.', 'origin' => 'this is test 8.', 'translationKey' => 'only.possible.with.unitTests.test8', 'setId' => 'setId'],
        ];

        $result = $mehtod->invokeArgs($service, [[$snippetFileMock], 'setId']);

        $this->silentAssertArraySubset($expectedResult, $result);
    }

    public function testGetSnippetFilesByIso(): void
    {
        $snippetFiles = [
            new de_AT(),
            new de_AT_e1(),
            new de_AT_e2(),
            new en_US(),
            new en_US_e1(),
            new en_US_e2(),
        ];

        $service = $this->getSnippetService($snippetFiles);
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getSnippetFilesByIso');

        $result1 = $mehtod->invoke($service, ['de_AT']);
        $result2 = $mehtod->invoke($service, ['en_US']);

        static::assertCount(3, $result1['de_AT']);
        static::assertCount(3, $result2['en_US']);
    }

    /**
     * @dataProvider dataProviderForTestSortSnippets
     */
    public function testSortSnippets($snippets, $sortParams, $expectedResult): void
    {
        $service = $this->getSnippetService();
        $result = ReflectionHelper::getMethod(SnippetService::class, 'sortSnippets')
            ->invokeArgs($service, [$sortParams, $snippets]);

        static::assertSame($expectedResult, $result);
    }

    public function dataProviderForTestSortSnippets(): array
    {
        $snippets = require __DIR__ . '/_fixtures/testSort/snippetsToSort.php';

        return [
            [[], [], []],
            [[], ['sortBy' => 'foo'], []],
            [$snippets, ['sortBy' => 'foo'], $snippets],
            [$snippets, ['sortBy' => 'foo', 'sortDirection' => 'DESC'], $snippets],

            [$snippets, ['sortBy' => 'translationKey'], $snippets],
            [$snippets, ['sortBy' => 'translationKey', 'sortDirection' => 'ASC'], $snippets],
            [$snippets, ['sortBy' => 'translationKey', 'sortDirection' => 'DESC'], require __DIR__ . '/_fixtures/testSort/expectedResultSort1.php'],

            [$snippets, ['sortBy' => '71a916e745114d72abafbfdc51cbd9d0'], $snippets],
            [$snippets, ['sortBy' => '71a916e745114d72abafbfdc51cbd9d0', 'sortDirection' => 'ASC'], require __DIR__ . '/_fixtures/testSort/expectedResultSort2.php'],
            [$snippets, ['sortBy' => '71a916e745114d72abafbfdc51cbd9d0', 'sortDirection' => 'DESC'], require __DIR__ . '/_fixtures/testSort/expectedResultSort3.php'],

            [$snippets, ['sortBy' => 'b8d2230a7b324e448c9c8b22ed1b89d8'], $snippets],
            [$snippets, ['sortBy' => 'b8d2230a7b324e448c9c8b22ed1b89d8', 'sortDirection' => 'ASC'], require __DIR__ . '/_fixtures/testSort/expectedResultSort4.php'],
            [$snippets, ['sortBy' => 'b8d2230a7b324e448c9c8b22ed1b89d8', 'sortDirection' => 'DESC'], require __DIR__ . '/_fixtures/testSort/expectedResultSort5.php'],
        ];
    }

    /**
     * @dataProvider dataProviderForTestGetList
     */
    public function testGetList($params, $expectedResult): void
    {
        $sql = file_get_contents(__DIR__ . '/_fixtures/testGetList/SetSql.sql');
        $this->getContainer()->get(Connection::class)->exec($sql);

        $collection = new SnippetFileCollection();
        $collection->add(new SnippetFile_foo_foo());
        $collection->add(new SnippetFile_bar_bar());

        $context = new Context(new SourceContext());

        $service = new SnippetService(
            $this->getContainer()->get(Connection::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository'),
            $this->getContainer()->get(SnippetFilterFactory::class)
        );

        $result = $service->getList($params['page'], $params['limit'], $context, $params['filter'], $params['sort']);

        static::assertSame($expectedResult, $result);
    }

    public function dataProviderForTestGetList(): array
    {
        $defaultParams = [
            'page' => 1,
            'limit' => 25,
            'filter' => [],
            'sort' => [],
        ];

        $limitTest = array_replace($defaultParams, ['limit' => 2]);
        $limitPageTest = array_replace($defaultParams, ['limit' => 2, 'page' => 3]);
        $filterAuthorTest = array_replace($defaultParams, ['filter' => ['author' => ['user/admin']]]);
        $filterCustomTest = array_replace($defaultParams, ['filter' => ['custom' => true]]);
        $filterEmptyTest = array_replace($defaultParams, ['filter' => ['empty' => true]]);
        $filterNamespaceTest = array_replace($defaultParams, ['filter' => ['namespace' => ['cc', 'unit']]]);
        $filterTerm1Test = array_replace($defaultParams, ['filter' => ['term' => '4']]);
        $filterTerm2Test = array_replace($defaultParams, ['filter' => ['term' => 'dd bar']]);
        $filterTerm3Test = array_replace($defaultParams, ['filter' => ['term' => 'onlyFor']]);
        $filterTranslationKeyTest = array_replace($defaultParams, ['filter' => ['translationKey' => ['aa.ff']]]);
        $filterTranslationKey2Test = array_replace($defaultParams, ['filter' => ['translationKey' => ['aa.ff', 'aa.aa']]]);
        $sortTest = array_replace($defaultParams, ['sort' => ['sortBy' => 'translationKey', 'sortDirection' => 'ASC'], 'filter' => ['translationKey' => ['aa.aa', 'aa.bb', 'aa.ff', 'aa.gg']]]);
        $sort2Test = array_replace($defaultParams, ['sort' => ['sortBy' => 'translationKey', 'sortDirection' => 'DESC'], 'filter' => ['translationKey' => ['aa.aa', 'aa.bb', 'aa.ff', 'aa.gg']]]);
        $sort3Test = array_replace($defaultParams, ['sort' => ['sortBy' => '0d141d4373f3417e9655c9a30185481a', 'sortDirection' => 'DESC'], 'filter' => ['translationKey' => ['aa.aa', 'aa.bb', 'aa.ff', 'aa.gg']]]);

        return [
            [$defaultParams, require __DIR__ . '/_fixtures/testGetList/result1.php'],
            [$limitTest, require __DIR__ . '/_fixtures/testGetList/result2.php'],
            [$limitPageTest, require __DIR__ . '/_fixtures/testGetList/result3.php'],
            [$filterAuthorTest, require __DIR__ . '/_fixtures/testGetList/result4.php'],
            [$filterCustomTest, require __DIR__ . '/_fixtures/testGetList/result4.php'],
            [$filterEmptyTest, require __DIR__ . '/_fixtures/testGetList/result5.php'],
            [$filterNamespaceTest, require __DIR__ . '/_fixtures/testGetList/result6.php'],
            [$filterTerm1Test, require __DIR__ . '/_fixtures/testGetList/result7.php'],
            [$filterTerm2Test, require __DIR__ . '/_fixtures/testGetList/result8.php'],
            [$filterTerm3Test, require __DIR__ . '/_fixtures/testGetList/result9.php'],
            [$filterTranslationKeyTest, require __DIR__ . '/_fixtures/testGetList/result10.php'],
            [$filterTranslationKey2Test, require __DIR__ . '/_fixtures/testGetList/result11.php'],
            [$sortTest, require __DIR__ . '/_fixtures/testGetList/result12.php'],
            [$sort2Test, require __DIR__ . '/_fixtures/testGetList/result13.php'],
            [$sort3Test, require __DIR__ . '/_fixtures/testGetList/result14.php'],
        ];
    }

    public function testGetEmptyList(): void
    {
        $collection = new SnippetFileCollection();
        $collection->add(new EmptySnippetFile());

        $service = new SnippetService(
            $this->getContainer()->get(Connection::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository'),
            $this->getContainer()->get(SnippetFilterFactory::class)
        );

        $result = $service->getList(0, 25, new Context(new SourceContext()), [], []);

        static::assertSame(['total' => 0, 'data' => []], $result);
    }

    private function getSnippetService(array $snippetFiles = []): SnippetService
    {
        $collection = $this->getContainer()->get(SnippetFileCollection::class);
        foreach ($snippetFiles as $snippetFile) {
            $collection->add($snippetFile);
        }

        return new SnippetService(
            $this->getContainer()->get(Connection::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository'),
            $this->getContainer()->get(SnippetFilterFactory::class)
        );
    }

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }
}
