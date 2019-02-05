<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Snippet\SnippetFlattener;
use Shopware\Core\Framework\Snippet\SnippetService;
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

        static::assertArraySubset($expectedResult, $result);
        static::assertNotEmpty($result);
        static::assertTrue(count($expectedResult) < count($result));
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

    public function testGetDefaultLocale_expect_en_GB(): void
    {
        $method = ReflectionHelper::getMethod(SnippetService::class, 'getDefaultLocale');
        $result = $method->invoke($this->getSnippetService());

        static::assertSame(Defaults::LOCALE_EN_GB_ISO, $result);
    }

    public function testFillBlankSnippets()
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

    public function testFetchSnippetsFromDatabase()
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
    public function testMergeSnippetsComparison(array $sets, $expectedResult)
    {
        $service = $this->getSnippetService();
        $mehtod = ReflectionHelper::getMethod(SnippetService::class, 'mergeSnippetsComparison');

        $result = $mehtod->invoke($service, $sets);

        static::assertArraySubset($expectedResult, $result);
    }

    public function DataProviderForTestMergeSnippetsComparison()
    {
        $parameter = require __DIR__ . '/_fixtures/SnippetComparison.php';

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

        static::assertArraySubset($expectedResult, $result);
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

        static::assertCount(3, $result1['de_AT']);
        static::assertCount(3, $result2['en_US']);
    }

    private function getSnippetService(array $snippetFiles = []): SnippetService
    {
        $collection = $this->getContainer()->get(SnippetFileCollection::class);
        foreach ($snippetFiles as $snippetFile) {
            $collection->add($snippetFile);
        }

        return new SnippetService(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(SnippetFlattener::class),
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
