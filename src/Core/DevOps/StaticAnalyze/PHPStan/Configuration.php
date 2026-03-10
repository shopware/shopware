<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Configuration
{
    /**
     * @param array<string, list<string>> $parameters
     */
    public function __construct(private array $parameters)
    {
    }

    /**
     * @return list<string>
     */
    public function getAllowedNonDomainExceptions(): array
    {
        return $this->parameters['allowedNonDomainExceptions'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getAllowedStorefrontRouteNamespaces(): array
    {
        return $this->parameters['allowedStorefrontRouteNamespaces'] ?? [];
    }
}
