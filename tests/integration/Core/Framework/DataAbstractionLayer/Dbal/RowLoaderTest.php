<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class RowLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private RowLoader $rowLoader;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->rowLoader = new RowLoader(static::getContainer()->get(Connection::class));

        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'p1'))->price(10)->stock(1)->build(),
            (new ProductBuilder($this->ids, 'p2'))->price(10)->stock(2)->build(),
            (new ProductBuilder($this->ids, 'p3'))->price(10)->stock(3)->build(),
        ], Context::createDefaultContext());
    }

    public function testOnlyTheGivenColumnsOfTheRowsWithTheGivenPrimaryKeysAreLoaded(): void
    {
        $rows = $this->rowLoader->load('product', [$this->primaryKey('p1'), $this->primaryKey('p3')], ['id', 'stock']);

        usort($rows, static fn (array $a, array $b): int => $a['stock'] <=> $b['stock']);
        static::assertSame([
            ['id' => $this->ids->getBytes('p1'), 'stock' => '1'],
            ['id' => $this->ids->getBytes('p3'), 'stock' => '3'],
        ], $rows);
    }

    public function testAllColumnsAreLoadedWhenNoneAreGiven(): void
    {
        $rows = $this->rowLoader->load('product', [$this->primaryKey('p2')]);

        static::assertCount(1, $rows);
        static::assertSame('p2', $rows[0]['product_number']);
        static::assertSame('2', $rows[0]['stock']);
    }

    /**
     * @return array<string, string>
     */
    private function primaryKey(string $key): array
    {
        return ['id' => $this->ids->getBytes($key), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)];
    }
}
