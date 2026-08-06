<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * Provides the consents that are registered in the container, tagged `shopware.consent.definition`.
 *
 * @internal
 */
#[Package('data-services')]
class TaggedConsentDefinitionProvider implements ConsentDefinitionProvider
{
    /**
     * @param iterable<ConsentDefinition> $definitions
     */
    public function __construct(private readonly iterable $definitions)
    {
    }

    public function getConsentDefinitions(): array
    {
        return iterator_to_array($this->definitions, false);
    }
}
