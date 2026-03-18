<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Service;

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

        $threshold = new \DateTimeImmutable('-' . $resendInterval . ' seconds');
        if ($sentDate > $threshold) {
            return;
        }

        // Update sent date as this serves as cooldown for subsequent login attempts
        $this->customerRepository->update([
            ['id' => $customer->getId(), 'doubleOptInEmailSentDate' => new \DateTimeImmutable()],
        ], $context->getContext());

        $this->sendDoubleOptInMail($customer, $context, $this->resolveDomainUrl($context));
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
    private function resolveDomainUrl(SalesChannelContext $context): string
    {
        $domainUrl = $this->systemConfigService->getString(
            'core.loginRegistration.doubleOptInDomain',
            $context->getSalesChannelId()
        );

        if ($domainUrl) {
            return $domainUrl;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()))
            ->setLimit(1);

        return $this->salesChannelDomainRepository
            ->search($criteria, $context->getContext())
            ->getEntities()
            ->first()?->getUrl() ?? '';
    }
}
