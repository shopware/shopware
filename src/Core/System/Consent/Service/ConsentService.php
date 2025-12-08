<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentEntity;
use Shopware\Core\System\Consent\ConsentHistoryEntity;
use Shopware\Core\System\Consent\ConsentState;
use Shopware\Core\System\Consent\ConsentType;
use Shopware\Core\System\Consent\DTO\ConsentDTO;

#[Package('data-services')]
class ConsentService
{
    public function __construct(private readonly EntityRepository $repository)
    { }

    /**
     * @return array<ConsentDTO>
     */
    public function list(): array
    {
        $consents = $this->fetchConsents();

        return array_map(static function (ConsentEntity $consent) {
            $status = ConsentState::REQUESTED;

            if(!\empty($consent->history)) {
                $latestEntry = end($consent->history);
                $status = ConsentState::from($latestEntry->state);
            }

            return new ConsentDTO(
                name: $consent->name,
                identifier: $consent->id,
                type: ConsentType::from($consent->type),
                status: $status,
            );
        }, $consents->getElements());
    }

    public function acceptConsent(string $name, Context $context): void
    {

    }

    public function revokeConsent(string $name, Context $context): void
    {

    }

    /**
     * @return EntityCollection<ConsentEntity>
     */
    private function fetchConsents(): EntityCollection
    {
        $analyticsConsent = new ConsentEntity();
        $analyticsConsent->id = '019afebec8ea7e888932caf75e2c9880';
        $analyticsConsent->name = 'analytics';
        $analyticsConsent->requiredPermissions = '["user_profile"]';

        $historyEntry = new ConsentHistoryEntity();
        $historyEntry->id = '019afebed00e7dd2a6711540faf4d396';
        $historyEntry->consentId = $analyticsConsent->id;
        $historyEntry->state = ConsentState::ACCEPTED->value;

        $analyticsConsent->history[] = $historyEntry;

        return new EntityCollection([
            $analyticsConsent,
        ]);
    }
}