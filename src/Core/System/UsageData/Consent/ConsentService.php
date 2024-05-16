<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\UsageDataException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService
{
    public const SYSTEM_CONFIG_KEY_CONSENT_STATE = 'core.usageData.consentState';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $systemConfigRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function requestConsent(): void
    {
        if ($this->hasConsentState()) {
            throw UsageDataException::consentAlreadyRequested();
        }

        $this->setConsentState(ConsentState::REQUESTED);
    }

    public function acceptConsent(): void
    {
        if ($this->isConsentAccepted()) {
            throw UsageDataException::consentAlreadyAccepted();
        }

        $this->setConsentState(ConsentState::ACCEPTED);
    }

    public function revokeConsent(): void
    {
        if ($this->isConsentRevoked()) {
            throw UsageDataException::consentAlreadyRevoked();
        }

        $this->setConsentState(ConsentState::REVOKED);
    }

    public function hasConsentState(): bool
    {
        return $this->getConsentState() !== null;
    }

    public function isConsentAccepted(): bool
    {
        return $this->getConsentState() === ConsentState::ACCEPTED;
    }

    public function isConsentRevoked(): bool
    {
        return $this->getConsentState() === ConsentState::REVOKED;
    }

    /**
     * Returns the last date when we still had the consent.
     * If we never had the consent before, null is returned.
     */
    public function getLastConsentIsAcceptedDate(): ?\DateTimeImmutable
    {
        if ($this->isConsentAccepted()) {
            return \DateTimeImmutable::createFromInterface($this->clock->now());
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('configurationKey', self::SYSTEM_CONFIG_KEY_CONSENT_STATE));
        $criteria->setLimit(1);
        $entitySearchResult = $this->systemConfigRepository->search($criteria, Context::createDefaultContext());
        $config = $entitySearchResult->first();

        $updatedAt = $config?->getUpdatedAt();

        return $updatedAt ? \DateTimeImmutable::createFromInterface($updatedAt) : null;
    }

    public function getConsentState(): ?ConsentState
    {
        $value = $this->systemConfigService->getString(static::SYSTEM_CONFIG_KEY_CONSENT_STATE);

        return ConsentState::tryFrom($value);
    }

    private function setConsentState(ConsentState $consentState): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY_CONSENT_STATE, $consentState->value);

        try {
            $this->dispatcher->dispatch(new ConsentStateChangedEvent($consentState));
        } catch (\Throwable) {
        }
    }
}
