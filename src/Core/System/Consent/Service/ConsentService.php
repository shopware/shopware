<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\Consent;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateHistoryItem;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService
{
    /**
     * @var array<string, Consent>|null
     */
    private ?array $consents = null;

    /**
     * @var array<string, ConsentState>
     */
    private ?array $states = null;

    public function __construct(
        private readonly ConsentRepository $consentRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @return array<ConsentState>
     */
    public function list(ConsentContext $context): array
    {
        $consents = $this->fetchConsents();
        $states = $this->fetchStates();

        return array_map(function (Consent $consent) use ($context, $states) {
            $identifier = $context->getIdentifierForScope($consent->scope);
            $key = $this->key(
                $consent->name,
                $consent->scope->value,
                $identifier
            );

            return $states[$key] ?? new ConsentState(
                name: $consent->name,
                scope: $consent->scope,
                identifier: $identifier,
                status: ConsentStatus::REQUESTED,
                actorId: null,
            );
        }, $consents);
    }

    public function getConsentStatus(string $name, ?string $identifier = null): ConsentState
    {
        $consent = $this->fetchConsent($name);

        if ($consent->scope !== ConsentScope::GLOBAL && $identifier === null) {
            throw ConsentException::identifierRequired();
        }

        $states = $this->fetchStates();

        $key = $this->key($consent->name, $consent->scope->value, $identifier);

        if (isset($states[$key])) {
            return $states[$key];
        }

        return new ConsentState(
            name: $consent->name,
            scope: $consent->scope,
            identifier: $identifier,
            status: ConsentStatus::REQUESTED,
            actorId: null,
        );
    }

    public function acceptConsent(string $name, string $identifier): void
    {
        $consent = $this->fetchConsent($name);

        $key = $this->key($consent->name, $consent->scope->value, $identifier);

        $states = $this->fetchStates();
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::ACCEPTED) {
            return;
        }

        $this->consentRepository->updateConsentState(
            $consent,
            $consent->scope === ConsentScope::GLOBAL ? null : $identifier,
            ConsentStatus::ACCEPTED,
            $identifier
        );

        $this->eventDispatcher->dispatch(new ConsentAcceptedEvent($consent, $identifier));

        $this->invalidateState();
    }

    public function revokeConsent(string $name, string $identifier): void
    {
        $consent = $this->fetchConsent($name);

        $key = $this->key($consent->name, $consent->scope->value, $identifier);

        $states = $this->fetchStates();
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::REVOKED) {
            return;
        }

        $this->consentRepository->updateConsentState(
            $consent,
            $consent->scope === ConsentScope::GLOBAL ? null : $identifier,
            ConsentStatus::REVOKED,
            $identifier
        );
        $this->eventDispatcher->dispatch(new ConsentRevokedEvent($consent, $identifier));

        $this->invalidateState();
    }

    /**
     * @return list<ConsentStateHistoryItem>
     */
    public function getHistory(string $name, string $identifier): array
    {
        $consent = $this->fetchConsent($name);
        $scopeIdentifier = $consent->scope === ConsentScope::GLOBAL ? null : $identifier;

        return $this->consentRepository->getHistory($consent->id, $scopeIdentifier);
    }

    /**
     * @return array<string, Consent>
     */
    private function fetchConsents(): array
    {
        if ($this->consents !== null) {
            return $this->consents;
        }

        $consents = $this->consentRepository->fetchAllConsents();

        return $this->consents = array_combine(
            array_column($consents, 'name'),
            $consents,
        );
    }

    private function fetchConsent(string $name): Consent
    {
        $consents = $this->fetchConsents();

        if (!isset($consents[$name])) {
            throw ConsentException::notFound($name);
        }

        return $consents[$name];
    }

    /**
     * @return array<string, ConsentState>
     */
    private function fetchStates(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        $states = [];

        foreach ($this->consentRepository->fetchAllConsentStates() as $state) {
            $states[$this->stateKey($state)] = $state;
        }

        return $this->states = $states;
    }

    private function stateKey(ConsentState $state): string
    {
        return $this->key($state->name, $state->scope->value, $state->identifier);
    }

    /**
     * @param value-of<ConsentScope> $scope
     */
    private function key(string $consentName, string $scope, ?string $identifier): string
    {
        if ($scope === ConsentScope::GLOBAL->value) {
            return $consentName . ':' . $scope;
        }

        return $consentName . ':' . $scope . ':' . $identifier;
    }

    private function invalidateState(): void
    {
        $this->states = null;
        $this->consents = null;
    }
}
