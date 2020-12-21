<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1595489705PreventOldListingPricesTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @dataProvider providerWriteListingPrices
     */
    public function testWriteListingPrices(array $update, $expected): void
    {
        // create connection
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $connection->executeUpdate(
            'UPDATE product SET listing_prices = :prices WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($id),
                'prices' => json_encode($update),
            ]
        );

        $row = $connection->fetchColumn(
            'SELECT listing_prices FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        if (\is_string($row)) {
            $row = json_decode($row, true);
        }
        static::assertEquals($expected, $row);
    }

    public function providerWriteListingPrices()
    {
        return [
            'write old structure' => [
                ['structs' => serialize(new ListingPriceCollection()), 'formatted' => ['gross' => 1]],
                null,
            ],
            'write new structure' => [
                ['c_' . Defaults::CURRENCY => ['gross' => 100]],
                ['c_' . Defaults::CURRENCY => ['gross' => 100]],
            ],
        ];
    }
}
