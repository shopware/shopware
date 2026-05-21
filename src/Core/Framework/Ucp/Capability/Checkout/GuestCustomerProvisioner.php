<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Provisions a guest customer for anonymous UCP agent checkouts.
 *
 * UCP allows agents to complete orders on behalf of buyers without a
 * pre-existing customer account. Shopware's CartOrderRoute requires a
 * customer record though, so for anonymous agent flows we materialise a
 * guest customer on the fly with the buyer data the agent provides
 * (or a synthetic UCP-tagged record if no buyer data is available).
 *
 * The guest customer is bound to the cart's context token and decays with it.
 *
 * @internal
 */
#[Package('framework')]
class GuestCustomerProvisioner
{
    public const UCP_GUEST_EMAIL_DOMAIN = 'ucp-guest.local';

    /**
     * @param EntityRepository<SalutationCollection> $salutationRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractSalesChannelContextFactory $contextFactory,
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly EntityRepository $salutationRepository,
        private readonly EntityRepository $countryRepository,
    ) {
    }

    /**
     * Ensure the current context has a customer attached; if not, create a
     * guest customer using buyer info from the UCP request (or a synthetic
     * placeholder).
     *
     * @param array<string, mixed> $buyer optional buyer object from UCP payload
     *
     * @return SalesChannelContext fresh context with the customer attached
     */
    public function provisionIfMissing(
        SalesChannelContext $context,
        array $buyer = [],
        string $contextToken = ''
    ): SalesChannelContext {
        if ($context->getCustomer() !== null) {
            return $context;
        }

        $contextToken = $contextToken !== '' ? $contextToken : $context->getToken();

        $email = $this->resolveEmail($buyer, $contextToken);
        $firstName = $this->resolveString($buyer, ['first_name', 'firstName'], 'UCP');
        $lastName = $this->resolveString($buyer, ['last_name', 'lastName'], 'Guest');

        $salutationId = $this->resolveSalutationId($context);
        $countryId = $this->resolveCountryId($context);

        $registrationData = new RequestDataBag([
            'guest' => true,
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'salutationId' => $salutationId,
            'storefrontUrl' => $this->resolveStorefrontUrl($context),
            'acceptedDataProtection' => true,
            'billingAddress' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'salutationId' => $salutationId,
                'street' => $buyer['billing_address']['street_address'] ?? 'UCP placeholder street 1',
                'zipcode' => $buyer['billing_address']['postal_code'] ?? '00000',
                'city' => $buyer['billing_address']['address_locality'] ?? 'UCP city',
                'countryId' => $countryId,
            ],
        ]);

        $customerResponse = $this->registerRoute->register($registrationData, $context, false);
        $customer = $customerResponse->getCustomer();

        // Persist the customer-token mapping for the cart's context token so a
        // freshly built SalesChannelContext sees the customer.
        $this->contextPersister->save(
            $contextToken,
            ['customerId' => $customer->getId()],
            $context->getSalesChannelId(),
            $customer->getId()
        );

        return $this->contextFactory->create(
            $contextToken,
            $context->getSalesChannelId(),
            array_filter([
                SalesChannelContextService::DOMAIN_ID => $context->getDomainId(),
                SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            ])
        );
    }

    /**
     * @param array<string, mixed> $buyer
     */
    private function resolveEmail(array $buyer, string $contextToken): string
    {
        $explicit = $buyer['email'] ?? null;
        if (\is_string($explicit) && filter_var($explicit, \FILTER_VALIDATE_EMAIL)) {
            return $explicit;
        }

        // Synthetic email — derived from cart token + timestamp + random to ensure uniqueness
        $unique = substr($contextToken, 0, 12) . '.' . dechex(random_int(0, 0xFFFFFFFF));

        return 'ucp-guest-' . $unique . '@' . self::UCP_GUEST_EMAIL_DOMAIN;
    }

    /**
     * @param array<string, mixed> $buyer
     * @param list<string> $candidates
     */
    private function resolveString(array $buyer, array $candidates, string $default): string
    {
        foreach ($candidates as $key) {
            $value = $buyer[$key] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $default;
    }

    private function resolveSalutationId(SalesChannelContext $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('salutationKey', 'not_specified'))->setLimit(1);
        $result = $this->salutationRepository->searchIds($criteria, $context->getContext());

        $id = $result->firstId();
        if (\is_string($id)) {
            return $id;
        }

        // Fallback: pick the first salutation we find
        $any = $this->salutationRepository->searchIds((new Criteria())->setLimit(1), $context->getContext())->firstId();

        return \is_string($any) ? $any : Uuid::randomHex();
    }

    private function resolveCountryId(SalesChannelContext $context): string
    {
        // Prefer the country already on the SalesChannel default
        $defaultCountry = $context->getSalesChannel()->getCountryId();
        if ($defaultCountry !== '') {
            return $defaultCountry;
        }

        $iso = $context->getShippingLocation()->getCountry()->getIso();
        if ($iso === null || $iso === '') {
            $iso = 'DE';
        }
        $criteria = (new Criteria())->addFilter(new EqualsFilter('iso', $iso))->setLimit(1);
        $id = $this->countryRepository->searchIds($criteria, $context->getContext())->firstId();

        return \is_string($id) ? $id : Uuid::randomHex();
    }

    private function resolveStorefrontUrl(SalesChannelContext $context): string
    {
        $domains = $context->getSalesChannel()->getDomains();
        if ($domains !== null && $domains->count() > 0) {
            $first = $domains->first();
            if ($first !== null) {
                return rtrim($first->getUrl(), '/');
            }
        }

        return 'http://localhost';
    }
}
