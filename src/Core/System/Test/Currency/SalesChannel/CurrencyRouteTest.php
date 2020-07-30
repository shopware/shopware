<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;

class CurrencyRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

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
                '/store-api/v' . PlatformRequest::API_VERSION . '/currency',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertContains($this->ids->get('currency'), array_column($response, 'id'));
        static::assertContains('FO', array_column($response, 'isoCode'));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/currency',
                [
                    'includes' => [
                        'currency' => ['isoCode'],
                    ],
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response);
        static::assertArrayNotHasKey('id', $response[0]);
        static::assertContains('te', array_column($response, 'isoCode'));
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/currency',
                [
                    'limit' => 1,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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
            ],
            [
                'id' => $this->ids->create('currency2'),
                'decimalPrecision' => 2,
                'name' => 'match2',
                'isoCode' => 'te',
                'shortName' => 'yay',
                'factor' => 1,
                'symbol' => 'B',
            ],
        ];

        $this->getContainer()->get('currency.repository')
            ->create($data, $this->ids->context);
    }
}
