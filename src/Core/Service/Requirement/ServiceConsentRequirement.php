<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Consent\ServiceConsent;
use Shopware\Core\System\Consent\Service\ConsentService;

/**
 * @internal
 *
 * This requirement gates the privilege granting step. When the general service consent is not accepted, privileges are not granted.
 */
#[Package('framework')]
class ServiceConsentRequirement implements ServiceRequirement
{
    public const NAME = 'service_consent';

    public function __construct(
        private readonly ConsentService $consentService,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getGate(): Gate
    {
        return Gate::PRIVILEGES;
    }

    public function isSatisfied(): bool
    {
        try {
            return $this->consentService
                ->getConsentState(ServiceConsent::NAME, Context::createDefaultContext())
                ->isCurrent();
        } catch (\Throwable) {
            return false;
        }
    }

    public function permitsStateChange(): bool
    {
        return true;
    }
}
