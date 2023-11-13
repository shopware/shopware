<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
#[Package('system-settings')]
class CountryStateRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response['elements']);
    }

    public function testSortByAlphabetical(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/country-state/' . $this->ids->get('countryId'),
                [
                    'limit' => 2,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['elements']);
        static::assertCount(2, $response['elements']);

        static::assertEquals([
            'Baden-Württemberg', 'Bavaria',
        ], array_map(fn (array $state) => $state['name'], $response['elements']));
    }
}
