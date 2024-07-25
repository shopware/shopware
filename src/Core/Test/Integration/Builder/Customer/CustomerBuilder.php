<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\Builder\Customer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestBuilderTrait;
use Shopware\Core\Test\TestDefaults;

/**
 * @final
 * How to use:
 * $x = (new CustomerBuilder(new IdsCollection(), 'p1'))
 *          ->firstName('Max')
 *          ->lastName('Muster')
 *          ->group('standard')
 *          ->build();
 */
#[Package('checkout')]
class CustomerBuilder
{
    use KernelTestBehaviour;
    use TestBuilderTrait;

    public string $id;

    protected string $firstName;

    protected string $lastName;

    protected string $email;

    protected string $customerGroupId;

    protected string $defaultBillingAddressId;

    /**
     * @var array<string, mixed>
     */
    protected array $defaultBillingAddress = [];

    protected string $defaultShippingAddressId;

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    protected string $defaultPaymentMethodId;

    /**
     * @var array<string, mixed>
     */
    protected array $addresses = [];

    /**
     * @var array<string, mixed>
     */
    protected array $group = [];

    /**
     * @deprecated tag:v6.7.0 - will be removed
     *
     * @var array<string, mixed>
     */
    protected array $defaultPaymentMethod = [];

    /**
     * @var array<string, mixed>
     */
    protected array $salutation = [];

    public function __construct(
        IdsCollection $ids,
        protected string $customerNumber,
        protected string $salesChannelId = TestDefaults::SALES_CHANNEL,
        string $customerGroup = 'customer-group',
        string $billingAddress = 'default-address',
        string $shippingAddress = 'default-address'
    ) {
        $this->ids = $ids;
        $this->id = $ids->create($customerNumber);
        $this->firstName = 'Max';
        $this->lastName = 'Mustermann';
        $this->email = 'max@mustermann.com';
        $this->salutation = self::salutation($ids);

        $this->customerGroup($customerGroup);
        $this->defaultBillingAddress($billingAddress);
        $this->defaultShippingAddress($shippingAddress);

        if (!Feature::isActive('v6.7.0.0')) {
            $this->defaultPaymentMethodId = self::connection()->fetchOne(
                'SELECT LOWER(HEX(payment_method_id))
                   FROM sales_channel_payment_method
                   JOIN payment_method ON sales_channel_payment_method.payment_method_id = payment_method.id
                   WHERE sales_channel_id = :id AND payment_method.active = true LIMIT 1',
                ['id' => Uuid::fromHexToBytes($salesChannelId)]
            );
        }
    }

    public function customerNumber(string $customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;
    }

    public function firstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function lastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function customerGroup(string $key): self
    {
        $this->customerGroupId = $this->ids->get($key);
        $this->group = [
            'id' => $this->ids->get($key),
            'name' => $key,
        ];

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function defaultBillingAddress(string $key, array $customParams = []): self
    {
        $this->addAddress($key, $customParams);

        $defaultBillingAddress = $this->addresses;
        $defaultBillingAddress[$key]['id'] = $this->ids->get($key);
        $this->defaultBillingAddress = $defaultBillingAddress[$key];
        $this->defaultBillingAddressId = $this->ids->get($key);

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function defaultShippingAddress(string $key, array $customParams = []): self
    {
        $this->addAddress($key, $customParams);
        $this->defaultShippingAddressId = $this->ids->get($key);

        return $this;
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed
     */
    public function defaultPaymentMethod(string $key): self
    {
        $this->defaultPaymentMethod = [
            'id' => $this->ids->get($key),
            'name' => $key,
        ];

        return $this;
    }

    /**
     * @param array<string, mixed> $customParams
     */
    public function addAddress(string $key, array $customParams = []): self
    {
        $address = \array_replace([
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'city' => 'Bielefeld',
            'salutation' => self::salutation($this->ids),
            'street' => 'Buchenweg 5',
            'zipcode' => '33062',
            'countryId' => $this->getCountry(),
        ], $customParams);

        $this->addresses[$key] = $address;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    private static function salutation(IdsCollection $ids): array
    {
        return [
            'id' => $ids->get('salutation'),
            'salutationKey' => 'salutation',
            'displayName' => 'test',
            'letterName' => 'test',
        ];
    }

    private static function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function getCountry(): string
    {
        return self::connection()->fetchOne(
            'SELECT LOWER(HEX(country_id)) FROM sales_channel_country WHERE sales_channel_id = :id LIMIT 1',
            ['id' => Uuid::fromHexToBytes($this->salesChannelId)]
        );
    }
}
