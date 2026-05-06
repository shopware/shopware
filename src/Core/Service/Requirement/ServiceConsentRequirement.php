<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Definition\ServiceConsent;
use Shopware\Core\System\Consent\Service\ConsentService;

/**
 * @internal
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
}
