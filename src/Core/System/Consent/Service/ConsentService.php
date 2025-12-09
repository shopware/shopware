<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\DTO\ConsentDTO;
use Shopware\Core\System\Consent\DTO\ConsentStateDTO;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\Consent\Storage\StorageInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentService
{
    /**
     * @var array<ConsentDTO>|null
     */
    private ?array $consents = null;

    /**
     * @var array<string, StorageInterface>
     */
    private readonly array $stores;

    /**
     * @param iterable<string, StorageInterface> $stores
     */
    public function __construct(
        private readonly ConsentRepository $consentRepository,
        iterable $stores,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->stores = iterator_to_array($stores);
    }

    /**
     * @return array<ConsentStateDTO>
     */
    public function list(string $userId): array
    {
        return array_map(
            fn (ConsentDTO $consent) => $this->storage($consent)->status($consent->name, $userId),
            $this->fetchConsents()
        );
    }

    public function getConsentStatus(string $name, string $identifier): ConsentStateDTO
    {
        return $this->storage($this->fetchConsent($name))->status($name, $identifier);
    }

    public function acceptConsent(string $name, string $identifier): void
    {
        $this->storage($this->fetchConsent($name))->accept($name, $identifier);

        $this->eventDispatcher->dispatch(new ConsentAcceptedEvent($name, $identifier));
    }

    public function revokeConsent(string $name, string $identifier): void
    {
        $this->storage($this->fetchConsent($name))->revoke($name, $identifier);

        $this->eventDispatcher->dispatch(new ConsentRevokedEvent($name, $identifier));
    }

    /**
     * @return array<ConsentDTO>
     */
    private function fetchConsents(): array
    {
        if ($this->consents !== null) {
            return $this->consents;
        }

        $consents = $this->consentRepository->fetchAll();

        return $this->consents = array_combine(
            array_column($consents, 'name'),
            $consents,
        );
    }

    private function fetchConsent(string $name): ConsentDTO
    {
        $this->fetchConsents();

        if (!isset($this->consents[$name])) {
            throw ConsentException::notFound($name);
        }

        return $this->consents[$name];
    }

    private function storage(ConsentDTO $consent): StorageInterface
    {
        return $this->stores[$consent->storage];
    }
}
