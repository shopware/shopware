<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Snippet\Files\de_DE\LanguageFile_de_DE;
use Shopware\Core\Framework\Snippet\Files\en_EN\LanguageFile_en_GB;
use Shopware\Core\Framework\Snippet\Files\LanguageFileCollection;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattener;
use Shopware\Core\Framework\Snippet\Services\SnippetService;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Test\Snippet\_fixtures\LaguageFileMock;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\de_AT;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\de_AT_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\de_AT_e2;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\en_US;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\en_US_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetLanguageFilesByIso\en_US_e2;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetServiceTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @param MessageCatalogueInterface $catalog
     * @param Context                   $context
     * @param array                     $expectedResult
     *
     * @dataProvider dataProviderForTestGetStoreFrontSnippets
     */
    public function testGetStoreFrontSnippets(MessageCatalogueInterface $catalog, array $expectedResult): void
    {
        $service = $this->getSnippetService();
        $result = $service->getStorefrontSnippets($catalog, Defaults::SNIPPET_BASE_SET_EN);

        $this->assertArraySubset($expectedResult, $result);
        $this->assertNotEmpty($result);
        $this->assertTrue(count($expectedResult) < count($result));
    }

    public function dataProviderForTestGetStoreFrontSnippets(): array
    {
        $context = $this->getContext(Defaults::SALES_CHANNEL);

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

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result_en_GB);
        $this->assertSame(Defaults::LOCALE_DE_DE_ISO, $result_de_DE);
    }

    public function testGetDefaultLocale_expect_en_GB(): void
    {
        $method = ReflectionHelper::getMethod(SnippetService::class, 'getDefaultLocale');
        $result = $method->invoke($this->getSnippetService());

        $this->assertSame(Defaults::LOCALE_EN_GB_ISO, $result);
    }

    /**
     * @param $searchTerm
     * @param $expected
     *
     * @dataProvider DataProviderForTestFindSnippetsInDatabase
     */
    public function testFindSnippetsInDatabase($searchTerm, $expectedResult)
    {
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.value', $searchTerm), 1));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.translationKey', $searchTerm), 1));

        $context = new Context(new SourceContext());

        $isoList = [
            Defaults::SNIPPET_BASE_SET_EN => 'en_GB',
            Defaults::SNIPPET_BASE_SET_DE => 'de_DE',
        ];

        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'findSnippetsInDatabase');
        $result = $mehtod->invokeArgs($this->getSnippetService(), [$criteria, $context, $searchTerm, $isoList]);

        $this->assertArraySubset($expectedResult, $result);
    }

    public function DataProviderForTestFindSnippetsInDatabase()
    {
        $sql = file_get_contents(__DIR__ . '/../_fixtures/snippets-for-searching.sql');
        $this->getContainer()->get(Connection::class)->executeQuery($sql);

        $results = require __DIR__ . '/../_fixtures/SearchingResultArray.php';

        return [
            ['', $results['result1']],
            ['documents', $results['result2']],
            ['string', $results['result3']],
            ['test', $results['result4']],
            ['Batman', $results['result5']],
            ['dogs', $results['result6']],
            ['Wissenschaftstheater', $results['result7']],
            ['Astronauten', $results['result8']],
        ];
    }

    /**
     * @param array  $isoList
     * @param array  $languageFiles
     * @param string $term
     * @param        $expectedResult
     *
     * @dataProvider dataProviderForTestGetSnippets
     */
    public function testGetSnippets(array $isoList, array $languageFiles, string $term, $expectedResult)
    {
        $sql = file_get_contents(__DIR__ . '/../_fixtures/snippets-for-searching.sql');
        $this->getContainer()->get(Connection::class)->executeQuery($sql);

        $languageFileMock = new LaguageFileMock();
        $service = $this->getSnippetService([$languageFileMock]);

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.value', $term), 1));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('snippet.translationKey', $term), 1));

        $context = new Context(new SourceContext());

        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getSnippets');
        $result = $mehtod->invokeArgs($service, [$languageFiles, $isoList, $criteria, $context]);

        $this->assertArraySubset($expectedResult, $result);
    }

    public function dataProviderForTestGetSnippets()
    {
        $expectedResults = require __DIR__ . '/../_fixtures/GetSnippetsResult.php';

        $sql = "INSERT IGNORE INTO `snippet_set` (`id`, `name`, `base_file`, `iso`, `created_at`, `updated_at`)
              VALUES (UNHEX('d25b3274612d4e6c960dadaf3ef56fd9'), 
              'only for unit tests', 
              'test_Unit_TEST', 
              'unit_TEST', 
              now(), 
              NULL);";

        $this->getContainer()->get(Connection::class)->executeQuery($sql);

        $unitTestFile = new LaguageFileMock();
        $deDEFile = new LanguageFile_de_DE();
        $enGBFile = new LanguageFile_en_GB();

        $isoList = [
            'd25b3274612d4e6c960dadaf3ef56fd9' => $unitTestFile->getIso(),
            Defaults::SNIPPET_BASE_SET_EN => $enGBFile->getIso(),
            Defaults::SNIPPET_BASE_SET_DE => $deDEFile->getIso(),
        ];

        return [
            [[], [], '', []],
            [$isoList, [$unitTestFile->getIso() => [$unitTestFile]], '', $expectedResults['result1']],
            [$isoList, [$deDEFile->getIso() => [$deDEFile]], '', $expectedResults['result2']],
            [$isoList, [$enGBFile->getIso() => [$enGBFile]], '', $expectedResults['result3']],
            [$isoList, [$unitTestFile->getIso() => [$unitTestFile]], 'test', $expectedResults['result4']],
            [$isoList, [$deDEFile->getIso() => [$deDEFile]], 'Löschen', $expectedResults['result5']],
        ];
    }

    public function testFillBlankSnippets()
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'fillBlankSnippets');

        $isoList = ['unit_TEST', 'en_GB'];
        $snippetList = [
            'unit_TEST' => [
                'snippets' => [
                    'required.unit.test.snippet' => 'This snippet is missing in the other language and needs to be filled up',
                ],
            ], [
                'en_GB' => [
                    'snippets' => [],
                ],
            ],
        ];

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

        $result = $mehtod->invokeArgs($service, [$isoList, $snippetList]);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetDbSnippetSets()
    {
        $connection = $this->getContainer()->get(Connection::class);
        $sql = file_get_contents(__DIR__ . '/../_fixtures/snippets-for-searching.sql');
        $connection->executeQuery($sql);

        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getDbSnippetSets');
        $translationKeyList = [
            'detail.descriptionHeader',
            'footer.newsletter',
            'documents.index.DocumentIndexHeadPosition',
        ];

        $select = $connection->createQueryBuilder()
            ->select(['snippet_set_id', 'id', 'translation_key AS translationKey', 'value', 'snippet_set_id AS setId'])
            ->from('snippet')
            ->where('translation_key IN (:translationKeyList)')
            ->setParameter('translationKeyList', $translationKeyList, Connection::PARAM_STR_ARRAY)
            ->execute()
            ->fetchAll();
        $select = FetchModeHelper::group($select);

        $expextedResult = require __DIR__ . '/../_fixtures/GetDbSnippetsResult.php';
        $result = $mehtod->invoke($service, $select);

        $this->assertSame($expextedResult, $result);
    }

    public function testFetchSnippetsFromDatabase()
    {
        $sql = file_get_contents(__DIR__ . '/../_fixtures/snippets-for-searching.sql');
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

        $this->assertSame($expextedResult, $result);
    }

    /**
     * @dataProvider dataProviderForTestMergeSnippets
     */
    public function testMergeSnippets(array $fileSnippets, array $dbSnippets, $snippetSetId, $expectedResult)
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'mergeSnippets');

        $result = $mehtod->invokeArgs($service, [$fileSnippets, $dbSnippets, $snippetSetId]);

        $this->assertArraySubset($expectedResult, $result);
    }

    public function dataProviderForTestMergeSnippets()
    {
        $parameter = require __DIR__ . '/../_fixtures/FileSnippets.php';

        return [
            [[], [], Defaults::SNIPPET_BASE_SET_EN, []],
            [$parameter['set1'], $parameter['dbSet1'], Defaults::SNIPPET_BASE_SET_EN, $parameter['result1']],
            [$parameter['set2'], $parameter['dbSet2'], Defaults::SNIPPET_BASE_SET_EN, $parameter['result2']],
            [$parameter['set3'], $parameter['dbSet3'], Defaults::SNIPPET_BASE_SET_EN, $parameter['result3']],
        ];
    }

    /**
     * @dataProvider DataProviderForTestMergeSnippetsComparison
     */
    public function testMergeSnippetsComparison(array $sets, $expectedResult)
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'mergeSnippetsComparison');

        $result = $mehtod->invoke($service, $sets);

        $this->assertArraySubset($expectedResult, $result);
    }

    public function DataProviderForTestMergeSnippetsComparison()
    {
        $parameter = require __DIR__ . '/../_fixtures/SnippetComparison.php';

        return [
            [[], []],
            [$parameter['set1'], $parameter['result1']],
            [$parameter['set2'], $parameter['result2']],
        ];
    }

    public function testGetSnippetsFromFiles()
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getSnippetsFromFiles');

        $languageFileMock = new LaguageFileMock();

        $expectedResult = [
            'only.possible.with.unitTests.test1' => 'this is test 1.',
            'only.possible.with.unitTests.test2' => 'this is test 2.',
            'only.possible.with.unitTests.test3' => 'this is test 3.',
            'only.possible.with.unitTests.test4' => 'this is test 4.',
            'only.possible.with.unitTests.test5' => 'this is test 5.',
            'only.possible.with.unitTests.test6' => 'this is test 6.',
            'only.possible.with.unitTests.test7' => 'this is test 7.',
            'only.possible.with.unitTests.test8' => 'this is test 8.',
        ];

        $result = $mehtod->invoke($service, [$languageFileMock]);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetLanguageFilesByIso()
    {
        $languageFiles = [
            new de_AT(),
            new de_AT_e1(),
            new de_AT_e2(),
            new en_US(),
            new en_US_e1(),
            new en_US_e2(),
        ];

        $service = $this->getSnippetService($languageFiles);
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'getLanguageFilesByIso');

        $result1 = $mehtod->invoke($service, ['de_AT']);
        $result2 = $mehtod->invoke($service, ['en_US']);

        $this->assertCount(3, $result1['de_AT']);
        $this->assertCount(3, $result2['en_US']);
    }

    private function getSnippetService(array $languageFiles = []): SnippetService
    {
        $collection = $this->getContainer()->get(LanguageFileCollection::class);
        foreach ($languageFiles as $languageFile) {
            $collection->add($languageFile);
        }

        return new SnippetService(
            $this->getContainer()->get('Doctrine\DBAL\Connection'),
            $this->getContainer()->get(SnippetFlattener::class),
            $collection,
            $this->getContainer()->get('snippet.repository')
        );
    }

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }

    private function getContext(string $salesChannelId): Context
    {
        $sourceContext = new SourceContext();
        $sourceContext->setSalesChannelId($salesChannelId);

        $context = Context::createDefaultContext();
        $property = ReflectionHelper::getProperty(Context::class, 'sourceContext');
        $property->setValue($context, $sourceContext);

        return $context;
    }
}
