<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

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
        private readonly EventDispatcherInterface $dispatcher,
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

    public function getConsentState(): ?ConsentState
    {
        $value = $this->systemConfigService->getString(static::SYSTEM_CONFIG_KEY_CONSENT_STATE);

        return ConsentState::tryFrom($value);
    }

    private function setConsentState(ConsentState $consentState): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY_CONSENT_STATE, $consentState->value);

        $this->dispatcher->dispatch(new ConsentStateChangedEvent($consentState));
    }
}
