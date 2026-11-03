<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Rule\IsGuestCustomerRule;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class CheckoutDraftRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    private string $germanyId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->germanyId = $this->getCountryId('DE');
        $this->createZipCodeShippingMethod();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'countryId' => $this->germanyId,
            'countries' => [['id' => $this->germanyId]],
            'shippingMethods' => [['id' => $this->ids->get('zip-shipping')]],
            'currencies' => [['id' => Defaults::CURRENCY], ['id' => $this->getCurrencyId('USD')]],
        ]);
    }

    public function testAnAddressMatchesGuestAndZipCodeRulesWithoutRegisteringACustomer(): void
    {
        $draft = $this->request('GET', ['methods' => true]);
        static::assertNotContains($this->ids->get('zip-shipping'), array_column($draft['shippingMethods'], 'id'));
        static::assertNull($draft['prospectiveCustomer']);
        $customers = $this->countCustomers();

        $draft = $this->request('PATCH', ['shippingAddress' => ['countryId' => $this->germanyId, 'zipcode' => '48624']]);

        static::assertContains($this->ids->get('zip-shipping'), array_column($draft['shippingMethods'], 'id'));
        static::assertSame([
            'countryId' => $this->germanyId,
            'countryStateId' => null,
            'zipcode' => '48624',
            'city' => null,
            'street' => null,
            'apiAlias' => 'prospective_customer_address',
        ], $draft['prospectiveCustomer']['shippingAddress']);
        static::assertSame($customers, $this->countCustomers(), 'No customer is written for an address');

        $draft = $this->request('GET');
        static::assertSame('48624', $draft['prospectiveCustomer']['shippingAddress']['zipcode'], 'The draft is stored in the context, so a reload can restore the form');
        static::assertContains($this->ids->get('zip-shipping'), array_column($draft['shippingMethods'], 'id'));
    }

    public function testSendingNullAsShippingAddressRemovesTheStoredAddress(): void
    {
        $this->request('PATCH', ['shippingAddress' => ['countryId' => $this->germanyId, 'zipcode' => '48624']]);

        $draft = $this->request('PATCH', ['shippingAddress' => null]);

        static::assertNull($draft['prospectiveCustomer']);
        static::assertNotContains($this->ids->get('zip-shipping'), array_column($draft['shippingMethods'], 'id'));
        static::assertNull($this->request('GET')['prospectiveCustomer']);
    }

    public function testPatchUsesTheCurrencyHeaderLikeGet(): void
    {
        $usdId = $this->getCurrencyId('USD');
        $this->browser->setServerParameter('HTTP_SW_CURRENCY_ID', $usdId);

        $draft = $this->request('PATCH', ['shippingAddress' => ['countryId' => $this->germanyId]]);

        static::assertSame($usdId, $draft['currency']['id']);
        static::assertSame($usdId, $this->request('GET')['currency']['id']);
    }

    public function testInvalidZipCodeIsReportedAtItsField(): void
    {
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->germanyId, 'checkPostalCodePattern' => true, 'defaultPostalCodePattern' => '\d{5}'],
        ], Context::createDefaultContext());

        $errors = $this->requestErrors(['shippingAddress' => ['countryId' => $this->germanyId, 'zipcode' => 'not-a-zip']]);

        static::assertSame('/shippingAddress/zipcode', $errors[0]['source']['pointer']);
    }

    public function testCountryStateHasToBelongToTheCountry(): void
    {
        $criteria = (new Criteria())->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('countryId', $this->germanyId)]))->setLimit(1);
        $foreignStateId = static::getContainer()->get('country_state.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($foreignStateId);

        $errors = $this->requestErrors(['shippingAddress' => ['countryId' => $this->germanyId, 'countryStateId' => $foreignStateId]]);

        static::assertSame('/shippingAddress/countryStateId', $errors[0]['source']['pointer']);
    }

    public function testUnknownFieldsAreRejectedInsteadOfIgnored(): void
    {
        $errors = $this->requestErrors(['customerGroupId' => Defaults::LANGUAGE_SYSTEM]);

        static::assertSame('FRAMEWORK__INVALID_REQUEST_PARAMETER', $errors[0]['code']);
    }

    public function testRegistrationReplacesTheProspectiveCustomerWithTheCustomer(): void
    {
        $this->request('PATCH', ['shippingAddress' => ['countryId' => $this->germanyId, 'zipcode' => '48624']]);

        $this->browser->jsonRequest('POST', '/store-api/account/register', [
            'guest' => true,
            'salutationId' => $this->getValidSalutationId(),
            'email' => 'prospect@example.com',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'storefrontUrl' => 'http://localhost',
            'acceptedDataProtection' => true,
            'billingAddress' => ['countryId' => $this->germanyId, 'street' => 'Ebbinghoff 10', 'zipcode' => '12345', 'city' => 'Schöppingen'],
        ]);
        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $draft = $this->request('GET');

        static::assertNull($draft['prospectiveCustomer']);
        static::assertNotContains($this->ids->get('zip-shipping'), array_column($draft['shippingMethods'], 'id'), 'The registered address is used from now on');
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $parameters = []): array
    {
        if ($method === 'GET') {
            $this->browser->request('GET', '/store-api/checkout/draft?' . http_build_query($parameters));
        } else {
            $this->browser->jsonRequest($method, '/store-api/checkout/draft', $parameters);
        }

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return list<array<string, mixed>>
     */
    private function requestErrors(array $parameters): array
    {
        $this->browser->jsonRequest('PATCH', '/store-api/checkout/draft', $parameters);

        $response = $this->browser->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];
    }

    private function countCustomers(): int
    {
        return static::getContainer()->get('customer.repository')->searchIds(new Criteria(), Context::createDefaultContext())->getTotal();
    }

    private function getCountryId(string $iso): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('iso', $iso));
        $id = static::getContainer()->get('country.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        return $id;
    }

    private function getCurrencyId(string $isoCode): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode));
        $id = static::getContainer()->get('currency.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        return $id;
    }

    private function createZipCodeShippingMethod(): void
    {
        static::getContainer()->get('shipping_method.repository')->create([[
            'id' => $this->ids->create('zip-shipping'),
            'name' => 'Only for new guests in 48624',
            'technicalName' => 'shipping_zip_guest',
            'active' => true,
            'availabilityRule' => [
                'id' => $this->ids->create('zip-rule'),
                'name' => 'Guest in 48624',
                'priority' => 1,
                'conditions' => [[
                    'type' => (new AndRule())->getName(),
                    'children' => [
                        ['type' => IsGuestCustomerRule::RULE_NAME, 'value' => ['isGuest' => true]],
                        ['type' => ShippingZipCodeRule::RULE_NAME, 'value' => ['operator' => Rule::OPERATOR_EQ, 'zipCodes' => ['48624']]],
                    ],
                ]],
            ],
            'deliveryTime' => [
                'name' => 'testDeliveryTime',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => [[
                'calculation' => 1,
                'quantityStart' => 1,
                'currencyPrice' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 10,
                    'gross' => 11,
                    'linked' => false,
                ]],
            ]],
        ]], Context::createDefaultContext());
    }
}
