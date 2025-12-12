<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateLogRecord;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService
{
    /**
     * @var array<string, ConsentDefinition>
     */
    private array $consentDefinitions;

    /**
     * @var array<string, ConsentState>
     */
    private ?array $states = null;

    /**
     * @param iterable<ConsentDefinition> $consentDefinitions
     */
    public function __construct(
        iterable $consentDefinitions,
        private readonly ConsentRepository $consentRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $definitions = [];
        foreach ($consentDefinitions as $definition) {
            $definitions[$definition->getName()] = $definition;
        }
        $this->consentDefinitions = $definitions;
    }

    /**
     * @return array<ConsentState>
     */
    public function list(ConsentContext $context): array
    {
        $states = $this->fetchStates();

        return array_map(function (ConsentDefinition $consent) use ($context, $states) {
            $identifier = $context->getIdentifierForScope($consent->getScope());
            $key = $this->key(
                $consent->getName(),
                $consent->getScope()->value,
                $identifier
            );

            return $states[$key] ?? new ConsentState(
                name: $consent->getName(),
                scope: $consent->getScope(),
                identifier: $identifier,
                status: ConsentStatus::REQUESTED,
                actorId: null,
            );
        }, $this->consentDefinitions);
    }

    public function getConsentState(string $name, ?string $identifier = null): ConsentState
    {
        $consent = $this->getConsentDefinition($name);

        if ($consent->getScope() !== ConsentScope::GLOBAL && $identifier === null) {
            throw ConsentException::identifierRequired();
        }

        $states = $this->fetchStates();

        $key = $this->key($consent->getName(), $consent->getScope()->value, $identifier);

        if (isset($states[$key])) {
            return $states[$key];
        }

        return new ConsentState(
            name: $consent->getName(),
            scope: $consent->getScope(),
            identifier: $identifier,
            status: ConsentStatus::REQUESTED,
            actorId: null,
        );
    }

    public function acceptConsent(string $name, string $identifier): void
    {
        $consent = $this->getConsentDefinition($name);

        $key = $this->key($consent->getName(), $consent->getScope()->value, $identifier);

        $states = $this->fetchStates();
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::ACCEPTED) {
            return;
        }

        $this->consentRepository->updateConsentState(
            $consent,
            $consent->getScope() === ConsentScope::GLOBAL ? null : $identifier,
            ConsentStatus::ACCEPTED,
            $identifier
        );

        $this->eventDispatcher->dispatch(new ConsentAcceptedEvent($consent->getName(), $consent->getScope(), $identifier));

        $this->invalidateState();
    }

    public function revokeConsent(string $name, string $identifier): void
    {
        $consent = $this->getConsentDefinition($name);

        $key = $this->key($consent->getName(), $consent->getScope()->value, $identifier);

        $states = $this->fetchStates();
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::REVOKED) {
            return;
        }

        $this->consentRepository->updateConsentState(
            $consent,
            $consent->getScope() === ConsentScope::GLOBAL ? null : $identifier,
            ConsentStatus::REVOKED,
            $identifier
        );
        $this->eventDispatcher->dispatch(new ConsentRevokedEvent($consent->getName(), $consent->getScope(), $identifier));

        $this->invalidateState();
    }

    /**
     * @return list<ConsentStateLogRecord>
     */
    public function getHistory(string $name, ?string $identifier = null): array
    {
        $consent = $this->getConsentDefinition($name);

        if ($consent->getScope() !== ConsentScope::GLOBAL && $identifier === null) {
            throw ConsentException::identifierRequired();
        }

        return $this->consentRepository->getHistory($consent->getName(), $identifier);
    }

    private function getConsentDefinition(string $name): ConsentDefinition
    {
        if (!isset($this->consentDefinitions[$name])) {
            throw ConsentException::notFound($name);
        }

        return $this->consentDefinitions[$name];
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

        foreach ($this->consentRepository->fetchAllConsentStates() as $record) {
            $state = ConsentState::fromDefinitionAndRecord(
                $this->getConsentDefinition($record->name),
                $record
            );

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
    }
}
