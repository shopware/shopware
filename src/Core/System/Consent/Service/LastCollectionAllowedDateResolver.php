<?php

declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentRepository;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;

/**
 * @internal
 */
#[Package('data-services')]
final class LastCollectionAllowedDateResolver
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly ConsentRepository $consentRepository,
    ) {
    }

    public function getLastCollectionAllowedDate(): ?\DateTimeImmutable
    {
        $state = $this->consentService->getConsentState(BackendData::NAME, Context::createDefaultContext());

        if ($state->status === ConsentStatus::ACCEPTED) {
            return new \DateTimeImmutable();
        }

        if ($state->status === ConsentStatus::REVOKED && $state->updatedAt !== null) {
            $revokedAt = new \DateTimeImmutable($state->updatedAt);
            $previousState = $this->consentRepository->getPreviousLoggedState(
                $state->name,
                $state->identifier,
                $revokedAt,
                ConsentStatus::REVOKED
            );
            if ($previousState !== ConsentStatus::ACCEPTED) {
                return null;
            }

            return $revokedAt;
        }

        return null;
    }
}
