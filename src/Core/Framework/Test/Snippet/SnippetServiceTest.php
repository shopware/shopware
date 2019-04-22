<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Snippet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Snippet\Files\SnippetFileCollection;
use Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
use Shopware\Core\Framework\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\Framework\Snippet\SnippetService;
use Shopware\Core\Framework\Test\Snippet\_fixtures\MockSnippetFile;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets\SnippetFile_de;
use Shopware\Core\Framework\Test\Snippet\_fixtures\testGetStoreFrontSnippets\SnippetFile_en;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class SnippetServiceTest extends TestCase
{
    use IntegrationTestBehaviour,
        AssertArraySubsetBehaviour;

    public static function tearDownAfterClass(): void
    {
        foreach (glob(__DIR__ . '/_fixtures/*.json') as $mockFile) {
            unlink($mockFile);
        }
    }

    /**
     * @dataProvider dataProviderForTestGetStoreFrontSnippets
     */
    public function testGetStoreFrontSnippets(MessageCatalogueInterface $catalog, array $expectedResult): void
    {
        $service = $this->getSnippetService(new SnippetFile_de(), new SnippetFile_en());

        $result = $service->getStorefrontSnippets($catalog, $this->getSnippetSetIdForLocale('en_GB'));

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

    public function getStorefrontSnippetsForNotExistingSnippetSet()
    {
        static::expectException(\InvalidArgumentException::class);

        $service = $this->getSnippetService();

        $service->getStorefrontSnippets($this->getCatalog([], 'en_GB'), Uuid::randomHex());
    }

    public function testGetRegionFilterItems(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'test.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getRegionFilterItems(Context::createDefaultContext());

        static::assertEquals([
            'bar',
            'foo',
            'test',
        ], $result);
    }

    public function testGetAuthors(): void
    {
        $snippetFile = new MockSnippetFile('foo', '{}');
        $snippetFile2 = new MockSnippetFile('Admin', '{}');

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.123',
            'value' => 'foo_123',
            'author' => 'test',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile, $snippetFile2);
        $result = $service->getAuthors(Context::createDefaultContext());

        static::assertCount(4, $result);

        static::assertContains('shopware', $result);
        static::assertContains('test', $result);
        static::assertContains('foo', $result);
        static::assertContains('Admin', $result);
    }

    public function testGetAuthorsWithoutDBAuthors(): void
    {
        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.123',
            'value' => 'foo_123',
            'author' => 'test',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService();
        $result = $service->getAuthors(Context::createDefaultContext());

        static::assertCount(2, $result);

        static::assertContains('shopware', $result);
        static::assertContains('test', $result);
    }

    public function testGetAuthorsFileAuthors(): void
    {
        $snippetFile = new MockSnippetFile('foo', '{}');
        $snippetFile2 = new MockSnippetFile('Admin', '{}');

        $service = $this->getSnippetService($snippetFile, $snippetFile2);
        $result = $service->getAuthors(Context::createDefaultContext());

        static::assertCount(2, $result);

        static::assertContains('foo', $result);
        static::assertContains('Admin', $result);
    }

    public function testGetListMergesFromFileAndDb(): void
    {
        $snippetFile = new MockSnippetFile('foo',
<<<json
{
    "foo": {
        "bar": "foo_bar"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.baz',
            'value' => 'foo_baz',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], []);

        static::assertSame(2, $result['total']);
        $this->assertSnippetResult($result, 'foo.bar', $fooId, 'foo_bar', 'foo_bar', 'foo_bar');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', '', 'foo_baz');
    }

    public function testGetListDbOverwritesFile(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "bar": "foo_bar"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.bar',
            'value' => 'foo_baz',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], []);

        static::assertSame(1, $result['total']);
        $this->assertSnippetResult($result, 'foo.bar', $fooId, 'foo_baz', '', 'foo_bar');
    }

    public function testGetListWithMultipleSets(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "bar": "foo_bar"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $barId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('snippet_set', [
            'id' => $barId,
            'name' => 'bar',
            'base_file' => 'bar',
            'iso' => 'bar',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'bar.baz',
            'value' => 'bar_baz',
            'author' => 'shopware',
            'snippet_set_id' => $barId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], []);

        static::assertSame(2, $result['total']);
        $this->assertSnippetResult($result, 'foo.bar', $fooId, 'foo_bar', 'foo_bar', 'foo_bar');
        $this->assertSnippetResult($result, 'bar.baz', $barId, 'bar_baz', '', 'bar_baz');
    }

    public function testGetListWithSameTranslationKeyInMultipleSets(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "bar": "foo_bar"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $barId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('snippet_set', [
            'id' => $barId,
            'name' => 'bar',
            'base_file' => 'bar',
            'iso' => 'bar',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.bar',
            'value' => 'bar_baz',
            'author' => 'shopware',
            'snippet_set_id' => $barId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], []);

        static::assertSame(1, $result['total']);
        foreach ($result['data']['foo.bar'] as $snippetSetData) {
            if ($snippetSetData['setId'] === Uuid::fromBytesToHex($fooId)) {
                static::assertSame('foo_bar', $snippetSetData['value']);
                continue;
            }
            if ($snippetSetData['setId'] === Uuid::fromBytesToHex($barId)) {
                static::assertSame('bar_baz', $snippetSetData['value']);
                continue;
            }

            static::assertEmpty($snippetSetData['value']);
        }
    }

    public function testGetListWithPagination(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "bar": "foo_bar",
        "foo": "foo_foo",
        "baz": "foo_baz",
        "bas": "foo_bas"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.test',
            'value' => 'foo_test',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 3, Context::createDefaultContext(), [], []);

        static::assertSame(5, $result['total']);
        static::assertCount(3, $result['data']);
        $data = $result['data'];

        $result = $service->getList(2, 3, Context::createDefaultContext(), [], []);
        static::assertSame(5, $result['total']);
        static::assertCount(2, $result['data']);
        $data = array_merge($data, $result['data']);

        $result = $service->getList(4, 3, Context::createDefaultContext(), [], []);
        static::assertSame(5, $result['total']);
        static::assertCount(0, $result['data']);

        $this->assertSnippetResult(['data' => $data], 'foo.bar', $fooId, 'foo_bar', 'foo_bar', 'foo_bar');
        $this->assertSnippetResult(['data' => $data], 'foo.foo', $fooId, 'foo_foo', 'foo_foo', 'foo_foo');
        $this->assertSnippetResult(['data' => $data], 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult(['data' => $data], 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult(['data' => $data], 'foo.test', $fooId, 'foo_test', '', 'foo_test');
    }

    public function testGetListSortsByTranslationKey(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], [
            'sortBy' => 'translationKey',
            'sortDirection' => 'ASC',
        ]);

        static::assertSame(4, $result['total']);

        $this->assertSnippetResult($result, 'bar.zz', $fooId, 'bar_zz', 'bar_zz', 'bar_zz');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');

        static::assertSame([
            'bar.zz',
            'foo.ab',
            'foo.bas',
            'foo.baz',
        ], array_keys($result['data']));
    }

    public function testGetListSortsByTranslationKeyDESC(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], [
            'sortBy' => 'translationKey',
            'sortDirection' => 'DESC',
        ]);

        static::assertSame(4, $result['total']);

        $this->assertSnippetResult($result, 'bar.zz', $fooId, 'bar_zz', 'bar_zz', 'bar_zz');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');

        static::assertSame([
            'foo.baz',
            'foo.bas',
            'foo.ab',
            'bar.zz',
        ], array_keys($result['data']));
    }

    public function testGetListSortsBySnippetSetId(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], [
            'sortBy' => Uuid::fromBytesToHex($fooId),
            'sortDirection' => 'ASC',
        ]);

        static::assertSame(4, $result['total']);

        $this->assertSnippetResult($result, 'bar.zz', $fooId, 'bar_zz', 'bar_zz', 'bar_zz');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');

        $this->assertFirstSnippetSetIdEquals($result, $fooId);

        static::assertSame([
            'bar.zz',
            'foo.ab',
            'foo.bas',
            'foo.baz',
        ], array_keys($result['data']));
    }

    public function testGetListSortsBySnippetSetIdDESC(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], [
            'sortBy' => Uuid::fromBytesToHex($fooId),
            'sortDirection' => 'DESC',
        ]);

        static::assertSame(4, $result['total']);

        $this->assertFirstSnippetSetIdEquals($result, $fooId);

        $this->assertSnippetResult($result, 'bar.zz', $fooId, 'bar_zz', 'bar_zz', 'bar_zz');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');

        static::assertSame([
            'foo.baz',
            'foo.bas',
            'foo.ab',
            'bar.zz',
        ], array_keys($result['data']));
    }

    public function testGetListIgnoresSortingForNotExistingSnippetSetId(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), [], [
            'sortBy' => Uuid::randomHex(),
        ]);

        static::assertSame(4, $result['total']);

        $this->assertSnippetResult($result, 'bar.zz', $fooId, 'bar_zz', 'bar_zz', 'bar_zz');
        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');

        static::assertSame([
            'bar.zz',
            'foo.ab',
            'foo.bas',
            'foo.baz',
        ], array_keys($result['data']));
    }

    public function testGetListFilters(): void
    {
        $snippetFile = new MockSnippetFile('foo',
            <<<json
{
    "foo": {
        "baz": "foo_baz",
        "bas": "foo_bas"
    },
    "bar": {
        "zz": "bar_zz"
    }
}
json
        );

        $fooId = Uuid::randomBytes();
        $connection = $this->getContainer()->get(Connection::class);

        $connection->insert('snippet_set', [
            'id' => $fooId,
            'name' => 'foo',
            'base_file' => 'foo',
            'iso' => 'foo',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'foo.ab',
            'value' => 'foo_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => 'bar.ab',
            'value' => 'bar_ab',
            'author' => 'shopware',
            'snippet_set_id' => $fooId,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);

        $service = $this->getSnippetService($snippetFile);
        $result = $service->getList(1, 25, Context::createDefaultContext(), ['namespace' => ['foo']], []);

        static::assertSame(3, $result['total']);

        $this->assertSnippetResult($result, 'foo.baz', $fooId, 'foo_baz', 'foo_baz', 'foo_baz');
        $this->assertSnippetResult($result, 'foo.bas', $fooId, 'foo_bas', 'foo_bas', 'foo_bas');
        $this->assertSnippetResult($result, 'foo.ab', $fooId, 'foo_ab', '', 'foo_ab');
    }

    public function testGetEmptyList(): void
    {
        $service = $this->getSnippetService(new MockSnippetFile('foo'));

        $result = $service->getList(0, 25, Context::createDefaultContext(), [], []);

        static::assertSame(['total' => 0, 'data' => []], $result);
    }

    private function getCatalog(array $messages, string $local): MessageCatalogueInterface
    {
        return new MessageCatalogue($local, $messages);
    }

    private function assertSnippetResult(
        array $result,
        string $translationKey,
        string $snippetSetId,
        string $value,
        string $originValue,
        string $resetValue
    ): void {
        foreach ($result['data'][$translationKey] as $snippetSetData) {
            if ($snippetSetData['setId'] !== Uuid::fromBytesToHex($snippetSetId)) {
                static::assertEmpty($snippetSetData['value']);
            } else {
                static::assertSame($value, $snippetSetData['value']);
                static::assertSame($originValue, $snippetSetData['origin']);
                static::assertSame($resetValue, $snippetSetData['resetTo']);
            }
        }
    }

    private function getSnippetService(SnippetFileInterface ...$snippetFiles): SnippetService
    {
        $collection = new SnippetFileCollection();
        foreach ($snippetFiles as $file) {
            $collection->add($file);
        }

        return new SnippetService(
            $this->getContainer()->get(Connection::class),
            $collection,
            $this->getContainer()->get('snippet.repository'),
            $this->getContainer()->get('snippet_set.repository'),
            $this->getContainer()->get(SnippetFilterFactory::class)
        );
    }

    private function assertFirstSnippetSetIdEquals(array $result, string $fooId): void
    {
        foreach ($result['data'] as $data) {
            static::assertSame(Uuid::fromBytesToHex($fooId), $data[0]['setId']);
        }
    }
}
