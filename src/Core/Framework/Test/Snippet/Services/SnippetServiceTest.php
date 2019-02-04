<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Services\SnippetFlattener;
use Shopware\Core\Framework\Snippet\Services\SnippetService;
use Shopware\Core\Framework\Test\Snippet\_fixtures\SnippetFileMock;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\de_AT_e2;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US_e1;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetSnippetFilesByIso\en_US_e2;
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

    public function testFillBlankSnippets()
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'fillBlankSnippets');

        $isoList = ['unit_TEST', 'en_GB'];

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

        $snippetFileMock = new SnippetFileMock();

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

        $result = $mehtod->invoke($service, [$snippetFileMock]);

        $this->assertSame($expectedResult, $result);
    }

    public function testGetSnippetFilesByIso()
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

        $this->assertCount(3, $result1['de_AT']);
        $this->assertCount(3, $result2['en_US']);
    }

    private function getSnippetService(array $snippetFiles = []): SnippetService
    {
        $collection = $this->getContainer()->get(SnippetFileCollection::class);
        foreach ($snippetFiles as $snippetFile) {
            $collection->add($snippetFile);
        }

        return new SnippetService(
            $this->getContainer()->get('Doctrine\DBAL\Connection'),
            $this->getContainer()->get(SnippetFlattener::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository')
        );
    }

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }
}
