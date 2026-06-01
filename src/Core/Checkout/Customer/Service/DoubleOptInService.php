<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerConfirmRegisterUrlEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class DoubleOptInService
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function sendDoubleOptInMail(
        CustomerEntity $customer,
        SalesChannelContext $context,
        string $domainUrl,
        ?string $redirectTo = null,
        ?string $redirectParameters = null
    ): void {
        $url = $domainUrl . $this->buildConfirmPath($customer, $context);

        if ($redirectTo) {
            $params = \is_string($redirectParameters) ? (\json_decode($redirectParameters, true) ?? []) : [];
            $url .= '&' . \http_build_query(array_merge(['redirectTo' => $redirectTo], $params));
        }

        $event = $customer->getGuest()
            ? new DoubleOptInGuestOrderEvent($customer, $context, $url)
            : new CustomerDoubleOptInRegistrationEvent($customer, $context, $url);

        $this->eventDispatcher->dispatch($event);
    }

    public function resendDoubleOptInMail(CustomerEntity $customer, SalesChannelContext $context): void
    {
        $resendInterval = $this->systemConfigService->getInt(
            'core.loginRegistration.doubleOptInResendInterval',
            $context->getSalesChannelId()
        );

        if ($resendInterval <= 0) {
            return;
        }

        $sentDate = $customer->getDoubleOptInEmailSentDate();
        if ($sentDate === null) {
            return;
        }

        $threshold = $this->clock->now()->modify('-' . $resendInterval . ' hours');
        if ($sentDate > $threshold) {
            return;
        }

        $this->sendDoubleOptInMail($customer, $context, $this->resolveDomainUrl($context, $customer->getLanguageId()));

        // Update sent date as this serves as cooldown for subsequent login attempts
        $this->customerRepository->update([
            ['id' => $customer->getId(), 'doubleOptInEmailSentDate' => $this->clock->now()],
        ], $context->getContext());
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    public function mapCustomerDoubleOptInData(array $customer, SalesChannelContext $context): array
    {
        $configKey = $customer['guest']
            ? 'core.loginRegistration.doubleOptInGuestOrder'
            : 'core.loginRegistration.doubleOptInRegistration';

        $doubleOptInRequired = $this->systemConfigService
            ->getBool($configKey, $context->getSalesChannelId());

        if (!$doubleOptInRequired) {
            return $customer;
        }

        $customer['doubleOptInRegistration'] = true;
        $customer['doubleOptInEmailSentDate'] = $this->clock->now();
        $customer['hash'] = Uuid::randomHex();

        return $customer;
    }

    private function buildConfirmPath(CustomerEntity $customer, SalesChannelContext $context): string
    {
        $urlTemplate = $this->systemConfigService->getString(
            'core.loginRegistration.confirmationUrl',
            $context->getSalesChannelId()
        ) ?: '/registration/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%';

        $emailHash = Hasher::hash($customer->getEmail(), 'sha1');

        $urlEvent = new CustomerConfirmRegisterUrlEvent(
            $context,
            $urlTemplate,
            $emailHash,
            $customer->getHash() ?? '',
            $customer
        );
        $this->eventDispatcher->dispatch($urlEvent);

        return str_replace(
            ['%%HASHEDEMAIL%%', '%%SUBSCRIBEHASH%%'],
            [$emailHash, $customer->getHash() ?? ''],
            $urlEvent->getConfirmUrl()
        );
    }

    /**
     * Resolves the base domain URL for confirmation links.
     *
     * Uses the `core.loginRegistration.doubleOptInDomain` system config when set,
     * and falls back to the first configured domain of the sales channel otherwise.
     * Intended for contexts where no HTTP request is available (e.g. Store API login).
     */
    private function resolveDomainUrl(SalesChannelContext $context, string $languageId): string
    {
        $domainUrl = $this->systemConfigService->getString(
            'core.loginRegistration.doubleOptInDomain',
            $context->getSalesChannelId()
        );

        if ($domainUrl) {
            return $domainUrl;
        }

        $domain = null;

        $domains = $context->getSalesChannel()->getDomains();
        // Domains should never be null, as they are loaded in the `BaseSalesChannelContextFactory`
        if ($domains !== null) {
            // If the domain id has been set, by the request, we use this domain id
            $domainId = $context->getDomainId();
            if ($domainId !== null) {
                $domain = $domains->get($domainId);
            }

            // Try to determine the correct domain by the customer language id
            if ($domain === null) {
                foreach ($domains as $d) {
                    if ($d->getLanguageId() === $languageId) {
                        $domain = $d;
                        break;
                    }
                }
            }
        }

        if ($domain === null) {
            $criteria = (new Criteria())
                ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
                ->setLimit(1);

            $domain = $this->salesChannelDomainRepository
                ->search($criteria, $context->getContext())
                ->getEntities()
                ->first();
        }

        return $domain?->getUrl() ?? '';
    }
}
