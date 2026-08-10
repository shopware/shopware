<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * Source of consent definitions. Registered with the `shopware.consent.definition_provider` DI tag
 * and read by `ConsentService`. The consents registered in the container come from
 * `TaggedConsentDefinitionProvider`; implement this interface for consents that only exist at
 * runtime, for example the ones declared by installed apps.
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
