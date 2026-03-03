<?php

declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Service\ConsentService;

/**
 * @internal
 */
#[Package('data-services')]
final class LastCollectionAllowedDateResolver
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    public function getCollectUntil(): ?\DateTimeImmutable
    {
        $state = $this->consentService->getConsentState(BackendData::NAME, Context::createDefaultContext());

        if ($state->status === ConsentStatus::ACCEPTED) {
            return new \DateTimeImmutable();
        }

        if ($state->status === ConsentStatus::REVOKED && $state->updatedAt !== null) {
            // last time the consent was revoked
            return new \DateTimeImmutable($state->updatedAt);
        }

        return null;
    }
}
