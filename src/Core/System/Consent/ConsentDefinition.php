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
}
