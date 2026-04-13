<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\DTO\ConsentStateRecord;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService implements ResetInterface
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
                status: ConsentStatus::UNSET,
                actor: null,
                updatedAt: null,
                acceptedRevision: null,
                latestRevision: $consent->getLatestRevision(),
            );
        }, $this->consentDefinitions);
    }

    public function getConsentState(string $name, Context $context): ConsentState
    {
        $consent = $this->getConsentDefinition($name);
        $key = $this->key($consent, $context);

        $states = $this->fetchStates($context);
        if (isset($states[$key])) {
            return $states[$key];
        }

        return new ConsentState(
            name: $consent->getName(),
            scopeName: $consent->getScopeName(),
            identifier: $this->getScope($consent)->resolveIdentifier($context),
            status: ConsentStatus::UNSET,
            actor: null,
            updatedAt: null,
            acceptedRevision: null,
            latestRevision: $consent->getLatestRevision(),
        );
    }

    public function acceptConsent(string $name, Context $context, ?string $revision = null): ConsentState
    {
        $consent = $this->getConsentDefinition($name);
        $this->validatePermissions($context, $consent);

        $stored = $this->findStoredStateRecord($consent, $context);

        if ($stored !== null && $stored->status === ConsentStatus::ACCEPTED) {
            if ($revision === null && $stored->revision === null) {
                return $this->createConsentState($consent, $stored, null);
            }
        }

        $revision = $this->resolveRevisionForAcceptance($consent, $revision);

        if ($stored !== null && $stored->status === ConsentStatus::ACCEPTED && $stored->revision === $revision) {
            return $this->createConsentState($consent, $stored, $revision);
        }

        $scope = $this->getScope($consent);

        $this->consentRepository->updateConsentState(
            $consent,
            $scope->resolveIdentifier($context),
            ConsentStatus::ACCEPTED,
            $scope->resolveActorIdentifier($context),
            $revision,
        );

        $this->invalidateState();

        $updatedState = $this->getConsentState($name, $context);

        \assert(\is_string($updatedState->actor));
        $this->eventDispatcher->dispatch(new ConsentAcceptedEvent($updatedState->name, $updatedState->scopeName, $updatedState->identifier, $updatedState->actor, $updatedState->acceptedRevision));

        return $updatedState;
    }

    public function revokeConsent(string $name, Context $context): ConsentState
    {
        $this->validatePermissions($context, $this->getConsentDefinition($name));
        $updatedState = $this->updateState($name, ConsentStatus::REVOKED, $context);

        \assert(\is_string($updatedState->actor));
        $this->eventDispatcher->dispatch(new ConsentRevokedEvent($updatedState->name, $updatedState->scopeName, $updatedState->identifier, $updatedState->actor));

        return $updatedState;
    }

    public function reset(): void
    {
        $this->invalidateState();
    }

    /**
     * If no revision is provided, the current latest revision is accepted implicitly.
     * If a revision is provided explicitly, it must still match the current latest revision.
     */
    private function resolveRevisionForAcceptance(ConsentDefinition $consent, ?string $revision): ?string
    {
        $latestRevision = $consent->getLatestRevision();

        if ($revision === null) {
            return $latestRevision;
        }

        if ($latestRevision === null || $revision !== $latestRevision) {
            throw ConsentException::invalidRevision($consent->getName(), $revision, $latestRevision);
        }

        return $latestRevision;
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

    private function findStoredStateRecord(ConsentDefinition $consent, Context $context): ?ConsentStateRecord
    {
        $identifier = $this->getScope($consent)->resolveIdentifier($context);

        foreach ($this->consentRepository->fetchAllConsentStates() as $record) {
            if ($record->name === $consent->getName() && $record->identifier === $identifier) {
                return $record;
            }
        }

        return null;
    }

    private function createConsentState(ConsentDefinition $consent, ConsentStateRecord $record, ?string $latestRevision): ConsentState
    {
        return new ConsentState(
            name: $consent->getName(),
            scopeName: $consent->getScopeName(),
            identifier: $record->identifier,
            status: $record->status,
            actor: $record->actor,
            updatedAt: $record->updatedAt,
            acceptedRevision: $record->revision,
            latestRevision: $latestRevision,
        );
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

    private function updateState(string $name, ConsentStatus $status, Context $context, ?string $revision = null): ConsentState
    {
        $consent = $this->getConsentDefinition($name);
        $revision = $status === ConsentStatus::ACCEPTED ? $revision : null;

        $key = $this->key($consent, $context);

        $states = $this->fetchStates($context);
        $stored = $states[$key] ?? null;

        if ($stored !== null) {
            if ($stored->status === $status && $stored->acceptedRevision === $revision) {
                return $stored;
            }

            if ($stored->status === ConsentStatus::DECLINED && $status === ConsentStatus::REVOKED) {
                return $stored;
            }
        }

        $scope = $this->getScope($consent);

        $this->consentRepository->updateConsentState(
            $consent,
            $scope->resolveIdentifier($context),
            $status,
            $scope->resolveActorIdentifier($context),
            $revision,
        );

        $this->invalidateState();

        return $this->getConsentState($name, $context);
    }

    private function validatePermissions(Context $context, ConsentDefinition $consent): void
    {
        $source = $context->getSource();

        \assert($source instanceof AdminApiSource);

        if ($source->isAdmin()) {
            return;
        }

        $missingPermissions = [];
        foreach ($consent->getRequiredPermissions() as $permission) {
            if (!$source->isAllowed($permission)) {
                $missingPermissions[] = $permission;
            }
        }

        if ($missingPermissions !== []) {
            throw ConsentException::insufficientPermissions($consent->getName(), $missingPermissions);
        }
    }
}
