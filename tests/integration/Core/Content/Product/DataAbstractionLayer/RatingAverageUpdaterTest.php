<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class RatingAverageUpdaterTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testResetClearsStaleFamilyRatingsOnlyInTheRequestedVersion(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $parent = Uuid::randomHex();
        $ratedVariant = Uuid::randomHex();
        $zeroVariant = Uuid::randomHex();
        $unratedVariant = Uuid::randomHex();
        $unrelated = Uuid::randomHex();
        $draft = Uuid::randomHex();
        $ratings = [$parent => 4.5, $ratedVariant => 3.0, $zeroVariant => 0.0, $unratedVariant => null, $unrelated => 2.0];

        foreach ([Defaults::LIVE_VERSION, $draft] as $version) {
            foreach ($ratings as $id => $rating) {
                $isVariant = $id !== $parent && $id !== $unrelated;
                $connection->insert('product', [
                    'id' => Uuid::fromHexToBytes($id),
                    'version_id' => Uuid::fromHexToBytes($version),
                    'product_number' => $id,
                    'stock' => 1,
                    'parent_id' => $isVariant ? Uuid::fromHexToBytes($parent) : null,
                    'parent_version_id' => $isVariant ? Uuid::fromHexToBytes($version) : null,
                    'rating_average' => $rating,
                ]);
            }
        }

        (new RatingAverageUpdater($connection))->update(
            [$parent, $ratedVariant, $parent],
            Context::createDefaultContext()->createWithVersionId($draft)
        );

        $rows = $connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) AS id, LOWER(HEX(version_id)) AS version, rating_average FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList(array_keys($ratings))],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(10, $rows);
        foreach ($rows as $row) {
            $expected = $row['version'] === $draft && $row['id'] !== $unrelated ? null : $ratings[$row['id']];
            static::assertSame($expected, $row['rating_average'] === null ? null : (float) $row['rating_average']);
        }
    }

    public function testBatchUpdateKeepsEachProductsAverage(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $ids = [];
        $expected = [];
        for ($index = 0; $index < 501; ++$index) {
            $id = Uuid::randomHex();
            $ids[] = $id;
            $connection->insert('product', [
                'id' => Uuid::fromHexToBytes($id),
                'version_id' => $version,
                'product_number' => $id,
                'stock' => 1,
                'rating_average' => 99,
            ]);
            $points = $index % 5 + 1;
            foreach ([1, $points] as $rating) {
                $connection->insert('product_review', [
                    'id' => Uuid::randomBytes(),
                    'product_id' => Uuid::fromHexToBytes($id),
                    'product_version_id' => $version,
                    'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'status' => 1,
                    'points' => $rating,
                    'title' => 'Review',
                    'content' => 'Review',
                    'created_at' => '2026-01-01 00:00:00.000',
                ]);
            }
            $expected[$id] = (1 + $points) / 2.0;
        }

        (new RatingAverageUpdater($connection))->update($ids, Context::createDefaultContext());

        $averages = $connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), rating_average FROM product WHERE id IN (:ids) AND version_id = :version',
            ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $version],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(501, $averages);
        foreach ($averages as $id => $average) {
            static::assertSame($expected[$id], (float) $average);
        }
    }
}
