<?php

declare(strict_types=1);

namespace Shopware\Core\System\Consent\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;

/**
 * @internal
 */
#[Package('data-services')]
final class ConsentDateResolver
{
    public function __construct(private readonly ConsentService $consentService)
    {
    }

    public function getLastConsentAcceptedDate(): ?\DateTimeImmutable
    {
        $state = $this->consentService->getConsentState(BackendData::NAME, Context::createDefaultContext());

        if ($state->status !== ConsentStatus::ACCEPTED || $state->updatedAt === null) {
            return null;
        }

        return new \DateTimeImmutable($state->updatedAt);
    }
}
