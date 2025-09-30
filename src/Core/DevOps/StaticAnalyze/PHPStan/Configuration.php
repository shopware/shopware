<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class Configuration
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(private array $parameters)
    {
    }

    /**
     * @return array<string>
     */
    public function getAllowedNonDomainExceptions(): array
    {
        return $this->parameters['allowedNonDomainExceptions'] ?? [];
    }
}
