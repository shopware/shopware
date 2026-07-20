<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
interface ConsentDefinition
{
    public function getName(): string;

    public function getScopeName(): string;

    public function getSince(): \DateTimeImmutable;

    /**
     * @return array<string>
     */
    public function getRequiredPermissions(): array;

    /**
     * The current/latest revision of this consent.
     * Returns null if this consent does not use revisions.
     *
     * The revision is an opaque string — the format is up to each definition.
     *
     * This method is called on every consent state read. Implementations that
     * resolve revisions from a remote source must use some form of caching: do
     * not make a remote call on every invocation.
     */
    public function getLatestRevision(): ?string;
}
