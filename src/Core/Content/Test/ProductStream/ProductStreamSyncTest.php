<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductStreamSyncTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testSyncProductStream(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'key' => 'test',
                'action' => SyncController::ACTION_UPSERT,
                'entity' => $this->getContainer()->get(ProductStreamDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id1,
                        'name' => 'Test stream',
                    ],
                    [
                        'id' => $id2,
                        'name' => 'Test stream - 2',
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), $response->getContent());

        $result = $this->connection
            ->executeQuery(
                'SELECT * FROM product_stream
                        INNER JOIN product_stream_translation ON product_stream.id = product_stream_translation.product_stream_id
                        WHERE product_stream.id = :id1
                          OR product_stream.id = :id2
                        ORDER BY `name`',
                [
                    'id1' => Uuid::fromHexToBytes($id1),
                    'id2' => Uuid::fromHexToBytes($id2),
                ]
            );

        static::assertEquals('Test stream', $result->fetch()['name']);
        static::assertEquals('Test stream - 2', $result->fetch()['name']);
    }
}
