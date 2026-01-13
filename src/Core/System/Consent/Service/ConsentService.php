<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService
{
    /**
     * @var array<string, ConsentScope>
     */
    private array $consentScopes;

    /**
     * @var array<string, ConsentDefinition>
     */
    private array $consentDefinitions;

    /**
     * @var array<string, ConsentState>
     */
    private ?array $states = null;

    /**
     * @param iterable<ConsentScope> $consentScopes
     * @param iterable<ConsentDefinition> $consentDefinitions
     */
    public function __construct(
        iterable $consentScopes,
        iterable $consentDefinitions,
        private readonly ConsentRepository $consentRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $scopes = [];
        foreach ($consentScopes as $scope) {
            $scopes[$scope->getName()] = $scope;
        }
        $this->consentScopes = $scopes;

        $definitions = [];
        foreach ($consentDefinitions as $definition) {
            $definitions[$definition->getName()] = $definition;
        }
        $this->consentDefinitions = $definitions;
    }

    /**
     * @return array<ConsentState>
     */
    public function list(Context $context): array
    {
        $states = $this->fetchStates($context);

        return array_map(function (ConsentDefinition $consent) use ($context, $states) {
            $key = $this->key($consent, $context);

            return $states[$key] ?? new ConsentState(
                name: $consent->getName(),
                scopeName: $consent->getScopeName(),
                identifier: $this->getScope($consent)->resolveIdentifier($context),
                status: ConsentStatus::REQUESTED,
                actorId: null,
            );
        }, $this->consentDefinitions);
    }

    public function getConsentState(string $name, Context $context): ConsentState
    {
        $this->getConsentDefinition($name);

        return $this->list($context)[$name];
    }

    public function acceptConsent(string $name, Context $context): void
    {
        $consent = $this->getConsentDefinition($name);

        $key = $this->key($consent, $context);

        $states = $this->fetchStates($context);
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::ACCEPTED) {
            return;
        }

        $scopeIdentifier = $this->getScope($consent)->resolveIdentifier($context);
        $this->consentRepository->updateConsentState(
            $consent,
            $scopeIdentifier,
            ConsentStatus::ACCEPTED,
            $this->getScope($consent)->resolveActorIdentifier($context)
        );

        $this->eventDispatcher->dispatch(new ConsentAcceptedEvent($consent->getName(), $consent->getScopeName(), $scopeIdentifier));

        $this->invalidateState();
    }

    public function revokeConsent(string $name, Context $context): void
    {
        $consent = $this->getConsentDefinition($name);

        $key = $this->key($consent, $context);

        $states = $this->fetchStates($context);
        if (isset($states[$key]) && $states[$key]->status === ConsentStatus::REVOKED) {
            return;
        }

        $scopeIdentifier = $this->getScope($consent)->resolveIdentifier($context);
        $this->consentRepository->updateConsentState(
            $consent,
            $scopeIdentifier,
            ConsentStatus::REVOKED,
            $this->getScope($consent)->resolveActorIdentifier($context)
        );
        $this->eventDispatcher->dispatch(new ConsentRevokedEvent($consent->getName(), $consent->getScopeName(), $scopeIdentifier));

        $this->invalidateState();
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
    private function fetchStates(Context $context): array
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

            $states[$this->key($state, $context)] = $state;
        }

        return $this->states = $states;
    }

    private function key(ConsentState|ConsentDefinition $consent, Context $context): string
    {
        if ($consent instanceof ConsentDefinition) {
            $scopeIdentifier = $this->getScope($consent)->resolveIdentifier($context);

            return $consent->getName() . ':' . $consent->getScopeName() . ':' . $scopeIdentifier;
        }

        // $consent is instance of ConsentState
        return $consent->name . ':' . $consent->scopeName . ':' . $consent->identifier;
    }

    private function invalidateState(): void
    {
        $this->states = null;
    }

    private function getScope(ConsentDefinition $consent): ConsentScope
    {
        if (!isset($this->consentScopes[$consent->getScopeName()])) {
            throw ConsentException::invalidScope($consent->getScopeName());
        }

        return $this->consentScopes[$consent->getScopeName()];
    }
}
