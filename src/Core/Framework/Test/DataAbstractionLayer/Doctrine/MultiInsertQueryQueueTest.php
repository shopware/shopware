<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MultiInsertQueryQueueTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNullableDatetime(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $query = new MultiInsertQueryQueue($connection);

        $date = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $catA = Uuid::randomBytes();
        $catB = Uuid::randomBytes();

        $query->addInsert(
            'category',
            [
                'id' => $catA,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => CategoryDefinition::TYPE_LINK,
                'created_at' => $date,
                'updated_at' => null,
            ]
        );
        $query->addInsert(
            'category',
            [
                'id' => $catB,
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => CategoryDefinition::TYPE_LINK,
                'created_at' => $date,
                'updated_at' => $date,
            ]
        );

        $query->execute();

        $actualA = $connection->fetchOne('SELECT updated_at FROM `category` WHERE id = :id', ['id' => $catA]);

        static::assertNotFalse($actualA);
        static::assertNull($actualA);

        $actualB = $connection->fetchOne('SELECT updated_at FROM `category` WHERE id = :id', ['id' => $catB]);

        static::assertNotFalse($actualB);
        static::assertSame($date, $actualB);
    }
}
