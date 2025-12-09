<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentEntity;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentHistoryEntity;
use Shopware\Core\System\Consent\ConsentPrototype;
use Shopware\Core\System\Consent\DTO\ConsentDTO;

#[Package('data-services')]
class ConsentService
{
    /**
     * @var null| array<ConsentDTO>
     */
    private $consents = null;

    /**
     * @param EntityRepository $repository
     * @param array<ConsentPrototype> $consentPrototypes
     */
    public function __construct(
        private readonly array $consentPrototypes,
    ){}

    /**
     * @return array<ConsentDTO>
     */
    public function list(): array
    {
        // maybe remove fields
        return $this->fetchConsents();
    }

    public function acceptConsent(string $name, string $identifier): void
    {
        $consent = $this->fetchConsent($name);

        $prototype = $this->consentPrototypes[$consent->type] ?? null;

        if ($prototype === null) {
            throw ConsentException::prototypeNotFound($consent->type);
        }

        $prototype->accept($name, $identifier);

        $this->dispatcher(new ConsentAcceptedEvent($name, $identifier));
    }

    public function revokeConsent(string $name, string $identifier): void
    {
        $consent = $this->fetchConsent($name);

        $prototype = $this->consentPrototypes[$consent->type] ?? null;

        if ($prototype === null) {
            throw ConsentException::prototypeNotFound($consent->name);
        }

        $prototype->revoke($name, $identifier);
    }

    /**
     * @return array<>
     */
    private function fetchConsents(): array
    {
        if ($this->consents !== null) {
            return $this->consents;
        }

        $this->consents = [
            'tracking_consent' => new ConsentDTO(
                name: 'tracking_consent',
                identifier: 'user_123',
                type: 'admin_user_consent',
                timestamp: new \DateTimeImmutable(),
            ),
            'backend_data_consent' => new ConsentDTO(
                name: 'backend_data_consent',
                identifier: 'user_123',
                type: 'system_config_consent',
                timestamp: new \DateTimeImmutable(),
            )
        ];
    }

    private function fetchConsent(string $name)
    {
        $this->fetchConsents();

        if (isset($this->consents[$name])) {
            throw ConsentException::notFound($name);
        }

        return $this->consents[$name];
    }
}