<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 * @group store-api
 */
class CountryStateRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->ids->set(
            'countryId',
            $this->getDeCountryId()
        );
    }

    public function testGetStates(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/country-state/' . $this->ids->get('countryId'),
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertCount(16, $response['elements']);
        static::assertContains($this->ids->get('countryId'), array_column($response['elements'], 'countryId'));
        static::assertContains('DE-HH', array_column($response['elements'], 'shortCode'));
    }

    public function testIncludes(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/country-state/' . $this->ids->get('countryId'),
                [
                    'includes' => [
                        'country_state' => ['shortCode'],
                    ],
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertCount(16, $response['elements']);
        static::assertArrayNotHasKey('id', $response['elements'][0]);
        static::assertContains('DE-HH', array_column($response['elements'], 'shortCode'));
    }

    public function testLimit(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/country-state/' . $this->ids->get('countryId'),
                [
                    'limit' => 2,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertCount(2, $response['elements']);
    }
}
