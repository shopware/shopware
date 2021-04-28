<?php

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1619604605FixListingPricesUsage;

class Migration1619604605FixListingPricesUsageTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testCategorySettings()
    {

    }

    public function testStreams(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'conditions' => [
                'id' => $ids->get('condition'),
                'type' => 'equals',
                'field' => 'listingPrices',
                'value' => 100
            ]
        ];

        $this->getContainer()->get('product_stream.repository')
            ->create([$stream], $ids->getContext());

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        $field = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT field FROM product_stream_filter WHERE id = :id',
                ['id' => $ids->getBytes('condition')]
            );

        static::assertEquals('cheapestPrice', $field);
    }
}
