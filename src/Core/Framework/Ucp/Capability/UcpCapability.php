<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DependencyInjection\UcpCapabilityCompilerPass;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Marker interface for UCP capabilities. Capability services are registered
 * via the DI tag `ucp.capability` with an explicit `name` attribute equal to
 * the UCP capability identifier (e.g. `dev.ucp.shopping.cart`).
 *
 * The {@see UcpCapabilityCompilerPass}
 * picks up tagged services and registers them in the {@see CapabilityRegistry}.
 */
#[Package('framework')]
interface UcpCapability
{
    public function getName(): string;

    public function getVersion(): string;

    public function getSpecUrl(): string;

    public function getSchemaUrl(): string;

    /**
     * The name of the parent capability if this is an extension, null if this
     * is a root capability. Multi-parent extensions return a comma-separated
     * list in declaration order.
     *
     * @return string|list<string>|null
     */
    public function getExtends(): string|array|null;

    /**
     * Optional per-business configuration object that is published in the
     * profile alongside this capability. Used e.g. by Identity Linking to
     * publish the OAuth scopes vocabulary.
     *
     * @return array<string, mixed>|null
     */
    public function getProfileConfig(): ?array;
}
