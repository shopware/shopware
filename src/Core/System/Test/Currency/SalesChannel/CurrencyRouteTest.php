<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class CurrencyRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $this->ids->get('currency'),
            'currencies' => [
                ['id' => $this->ids->get('currency')],
                ['id' => $this->ids->get('currency2')],
            ],
        ]);
    }

    public function testCurrencies(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response);
        static::assertContains($this->ids->get('currency'), array_column($response, 'id'));
        static::assertContains('FO', array_column($response, 'isoCode'));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                    'includes' => [
                        'currency' => ['isoCode'],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response);
        static::assertArrayNotHasKey('id', $response[0]);
        static::assertContains('te', array_column($response, 'isoCode'));
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/currency',
                [
                    'limit' => 1,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('currency'),
                'decimalPrecision' => 2,
                'name' => 'match',
                'isoCode' => 'FO',
                'shortName' => 'test',
                'factor' => 1,
                'symbol' => 'A',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            ],
            [
                'id' => $this->ids->create('currency2'),
                'decimalPrecision' => 2,
                'name' => 'match2',
                'isoCode' => 'te',
                'shortName' => 'yay',
                'factor' => 1,
                'symbol' => 'B',
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            ],
        ];

        $this->getContainer()->get('currency.repository')
            ->create($data, Context::createDefaultContext());
    }
}
