<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * Source of consent definitions that only exist at runtime, for example the consents declared by
 * installed apps. Registered with the `shopware.consent.definition_provider` DI tag and read by
 * `ConsentDefinitionRegistry` next to the definitions tagged `shopware.consent.definition`.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('data-services')]
interface ConsentDefinitionProvider
{
    /**
     * @return list<ConsentDefinition>
     */
    public function getConsentDefinitions(): array;
}
