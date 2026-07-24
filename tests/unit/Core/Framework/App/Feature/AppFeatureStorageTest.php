<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Feature;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFeatureStorage::class)]
class AppFeatureStorageTest extends TestCase
{
    public function testDeleteForAppDeletesFeaturesByBinaryAppId(): void
    {
        $appId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                'DELETE FROM `app_feature` WHERE `app_id` = :appId',
                ['appId' => Uuid::fromHexToBytes($appId)],
            );

        $storage = new AppFeatureStorage(
            $connection,
            static::createStub(ClockInterface::class),
            static::createStub(AppFeatureDefinitionRegistry::class),
        );

        $storage->deleteForApp($appId);
    }
}
