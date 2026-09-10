<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * Covers the whole path a company account without a contact person takes, because making optional one
 * validation entry point while another still rejects the empty name is not visible in isolation.
 */
#[Package('checkout')]
#[Group('store-api')]
class CompanyAccountNameTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->addCountriesToSalesChannel([], $this->ids->get('sales-channel'));
        $this->assignSalesChannelContext($this->browser);

        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testCompanyAccountRegistersWithoutAContactPerson(): void
    {
        $this->setNameFields(show: true, required: false);

        $this->register($this->companyRegistrationData());

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $customer = $this->loadCustomer('company-no-contact@example.com');

        static::assertSame('', $customer->getFirstName());
        static::assertSame('', $customer->getLastName());
        $billingAddress = $customer->getDefaultBillingAddress();
        static::assertNotNull($billingAddress);
        static::assertSame('', $billingAddress->getFirstName());
        static::assertSame('', $billingAddress->getLastName());
        static::assertSame('Acme GmbH', $customer->getCompany());
        static::assertSame('Acme GmbH', $customer->getDisplayName());
    }

    public function testCompanyAccountRegistersWithTheNameFieldsHidden(): void
    {
        $this->setNameFields(show: false, required: true);

        $this->register($this->companyRegistrationData());

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $customer = $this->loadCustomer('company-no-contact@example.com');

        static::assertSame('', $customer->getFirstName());
        static::assertSame('', $customer->getLastName());
        static::assertSame('Acme GmbH', $customer->getCompany());
    }

    public function testCompanyAddressStaysEditableWithoutAContactPerson(): void
    {
        $this->setNameFields(show: true, required: false);
        $this->register($this->companyRegistrationData());
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $customer = $this->loadCustomer('company-no-contact@example.com');
        $addressId = $customer->getDefaultBillingAddressId();

        $this->browser->request(
            'PATCH',
            '/store-api/account/address/' . $addressId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'company' => 'Acme GmbH',
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Hamburg',
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            Response::HTTP_OK,
            $this->browser->getResponse()->getStatusCode(),
            'a company address without a contact person has to stay editable: ' . (string) $this->browser->getResponse()->getContent()
        );
    }

    public function testProfileKeepsTheCompanyWhenTheFormDoesNotPostIt(): void
    {
        $this->setNameFields(show: true, required: false);
        $this->register($this->companyRegistrationData());
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->changeProfile();

        static::assertSame(
            Response::HTTP_OK,
            $this->browser->getResponse()->getStatusCode(),
            'a company account without a contact person has to stay savable: ' . (string) $this->browser->getResponse()->getContent()
        );

        static::assertSame('Acme GmbH', $this->loadCustomer('company-no-contact@example.com')->getCompany());
    }

    /**
     * Without the account type selection a shop cannot tell a commercial registration from a private
     * one, so an account that once had the choice goes back to a mandatory contact person.
     */
    public function testProfileNeedsAContactPersonAgainWithoutTheAccountTypeSelection(): void
    {
        $this->setNameFields(show: true, required: false);
        $this->register($this->companyRegistrationData());
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', false);

        $this->changeProfile();

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->browser->getResponse()->getStatusCode(),
            'the two settings must do nothing while the account type selection is off'
        );
    }

    public function testProfileStillNeedsACompanyWhenTheFormDoesNotPostOne(): void
    {
        $this->setNameFields(show: true, required: false);
        $this->register($this->companyRegistrationData());
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->customerRepository->update(
            [['id' => $this->loadCustomer('company-no-contact@example.com')->getId(), 'company' => '']],
            Context::createDefaultContext()
        );

        $this->changeProfile();

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->browser->getResponse()->getStatusCode(),
            'an account with neither a contact person nor a company has no name left'
        );
    }

    public function testSwitchingToACompanyAccountStillNeedsACompanyName(): void
    {
        $this->setNameFields(show: true, required: true);
        $this->register($this->privateRegistrationData());
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'salutationId' => $this->getValidSalutationId(),
                'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                'firstName' => 'Ada',
                'lastName' => 'Lovelace',
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->browser->getResponse()->getStatusCode(),
            'a request that names itself a company account has to bring a company'
        );
    }

    public function testACompanyNamedZeroSurvivesRegistration(): void
    {
        $this->setNameFields(show: true, required: false);

        $data = $this->companyRegistrationData();
        $data['billingAddress']['company'] = '0';

        $this->register($data);

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $customer = $this->loadCustomer('company-no-contact@example.com');

        static::assertSame('0', $customer->getCompany());
        static::assertSame('0', $customer->getDisplayName());
    }

    public function testCompanyAccountStillNeedsACompanyName(): void
    {
        $this->setNameFields(show: true, required: false);

        $data = $this->companyRegistrationData();
        unset($data['billingAddress']['company']);

        $this->register($data);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
    }

    public function testContactPersonStaysRequiredByDefault(): void
    {
        $this->setNameFields(show: true, required: true);

        $this->register($this->companyRegistrationData());

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->browser->getResponse()->getStatusCode(),
            'the contact person has to stay mandatory until a shop opts out'
        );
    }

    public function testPrivateAccountAlwaysNeedsAContactPerson(): void
    {
        $this->setNameFields(show: true, required: false);

        $data = $this->companyRegistrationData();
        $data['accountType'] = CustomerEntity::ACCOUNT_TYPE_PRIVATE;

        $this->register($data);

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->browser->getResponse()->getStatusCode(),
            'the optional contact person is scoped to company accounts'
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function register(array $data): void
    {
        $this->browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );

        $token = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($token !== null) {
            $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $token);
        }
    }

    private function changeProfile(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => '',
                'lastName' => '',
            ], \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function companyRegistrationData(): array
    {
        return [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => $this->getValidSalutationId(),
            'password' => '12345678',
            'email' => 'company-no-contact@example.com',
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'company' => 'Acme GmbH',
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privateRegistrationData(): array
    {
        $data = $this->companyRegistrationData();
        $data['accountType'] = CustomerEntity::ACCOUNT_TYPE_PRIVATE;
        $data['firstName'] = 'Ada';
        $data['lastName'] = 'Lovelace';
        $data['billingAddress']['firstName'] = 'Ada';
        $data['billingAddress']['lastName'] = 'Lovelace';
        unset($data['billingAddress']['company']);

        return $data;
    }

    private function setNameFields(bool $show, bool $required): void
    {
        $this->systemConfigService->set(CompanyAccountNameFields::CONFIG_SHOW, $show);
        $this->systemConfigService->set(CompanyAccountNameFields::CONFIG_REQUIRED, $required);
        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', true);
    }

    private function loadCustomer(string $email): CustomerEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('email', $email));
        $criteria->addAssociation('defaultBillingAddress');
        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        return $customer;
    }
}
