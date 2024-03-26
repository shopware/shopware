<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
final class Configuration
{
    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @return array<string>
     */
    public function getAllowedNonDomainExceptions(): array
    {
        return $this->parameters['allowedNonDomainExceptions'] ?? [];
    }
}
