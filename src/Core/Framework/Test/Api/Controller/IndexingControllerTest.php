<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class IndexingControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        // TODO: NEXT-13105 - Remove this skipping test after fixing the test error happens on mysql 8.0
        static::markTestSkipped('Need to fix this test failure with mysql 8.0');

        Feature::skipTestIfActive('FEATURE_NEXT_10552', $this);

        parent::setUp();

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testIterateIndexerApiShouldReturnFinishTrueWithInvalidIndexer(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/_action/indexing/test.indexer',
            ['offset' => 0]
        );
        $response = $this->getBrowser()->getResponse();
        $response = json_decode($response->getContent(), true);

        static::assertTrue($response['finish']);
    }

    /**
     * @dataProvider provideOffsets
     */
    public function testIterateIndexerApiShouldReturnCorrectOffset(int $offset): void
    {
        $database = $this->connection->fetchColumn('select database();');
        $autoIncrement = $this->connection->fetchColumn(
            'SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :database AND TABLE_NAME = "product"',
            ['database' => $database]
        );

        $offset = $offset + $autoIncrement - 1;

        $this->createProducts();

        $this->getBrowser()->request(
            'POST',
            '/api/v' . PlatformRequest::API_VERSION . '/_action/indexing/product.indexer',
            ['offset' => $offset]
        );
        $response = $this->getBrowser()->getResponse();
        $response = json_decode($response->getContent(), true);

        if ($offset - $autoIncrement + 1 === 100) {
            static::assertTrue($response['finish']);
        } else {
            static::assertFalse($response['finish']);
            static::assertEquals(['offset' => $offset + 50], $response['offset']);
        }
    }

    public function provideOffsets(): array
    {
        return [
            'offset 0' => [0],
            'offset 50' => [50],
            'offset 100' => [100],
        ];
    }

    private function createProducts(): void
    {
        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data[] = [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ];
        }
        $this->productRepository->create($data, Context::createDefaultContext());
    }
}
