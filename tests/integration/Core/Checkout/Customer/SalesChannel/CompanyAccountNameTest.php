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
 * Covers the whole path a company account without a contact person takes, because relaxing one
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
            'the relaxation is scoped to company accounts'
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
