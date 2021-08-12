<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1627929168UpdatePriceFieldInProductTable;

class Migration1627929168UpdatePriceFieldInProductTableTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @dataProvider dataProvider
     */
    public function testUpdatePriceColumn(array $price, ?array $percentageResult): void
    {
        $productId = $this->createProduct($price);

        $migration = new Migration1627929168UpdatePriceFieldInProductTable();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$productId]);

        /** @var ProductEntity $customer */
        $product = $this->getContainer()->get('product.repository')->search($criteria, Context::createDefaultContext())->first();
        $price = $product->getPrice();

        foreach ($price as $p) {
            $currencyId = $p->getCurrencyId();
            static::assertEquals($percentageResult[$currencyId], $p->getPercentage());
        }
    }

    public function dataProvider(): array
    {
        $currencyId = $this->getCurrencyId('USD');

        return [
            'Product with list price' => [
                [
                    Defaults::CURRENCY => [
                        'gross' => 5,
                        'net' => 5,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                        'listPrice' => [
                            'gross' => 10,
                            'net' => 10,
                            'linked' => false,
                            'currencyId' => Defaults::CURRENCY,
                        ],
                    ],
                ],
                [
                    Defaults::CURRENCY => ['net' => 50, 'gross' => 50],
                ],
            ],
            'Product has different gross and net' => [
                [
                    Defaults::CURRENCY => [
                        'gross' => 10,
                        'net' => 9.6,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                        'listPrice' => [
                            'gross' => 20,
                            'net' => 12,
                            'linked' => false,
                            'currencyId' => Defaults::CURRENCY,
                        ],
                    ],
                ],
                [
                    Defaults::CURRENCY => ['net' => 20, 'gross' => 50],
                ],
            ],
            'Product has no list price' => [
                [
                    Defaults::CURRENCY => [
                        'gross' => 5,
                        'net' => 5,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
                [
                    Defaults::CURRENCY => null,
                ],
            ],
            'Product has different currencies' => [
                [
                    Defaults::CURRENCY => [
                        'gross' => 10,
                        'net' => 9.6,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                        'listPrice' => [
                            'gross' => 20,
                            'net' => 12,
                            'linked' => false,
                            'currencyId' => Defaults::CURRENCY,
                        ],
                    ],
                    $currencyId => [
                        'gross' => 5,
                        'net' => 5,
                        'linked' => true,
                        'currencyId' => $currencyId,
                        'listPrice' => [
                            'gross' => 10,
                            'net' => 10,
                            'linked' => false,
                            'currencyId' => $currencyId,
                        ],
                    ],
                ],
                [
                    Defaults::CURRENCY => ['net' => 20, 'gross' => 50],
                    $currencyId => ['net' => 50, 'gross' => 50],
                ],
            ],
        ];
    }

    private function createProduct(array $price = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => $price,
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $id;
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->getContainer()->get('currency.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->first();

        return $currency !== null ? $currency->getId() : null;
    }
}
