<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental
 *
 * Read protection is currently experimental, as it is not guaranteed that the right scope is consistently
 * This can lead to unexpected side effects
 */
#[Package('core')]
class ReadProtection extends EntityProtection
{
    final public const PROTECTION = 'read_protection';

    /**
     * @var array<string, bool>
     */
    private array $allowedScopes = [];

    public function __construct(string ...$allowedScopes)
    {
        foreach ($allowedScopes as $scope) {
            $this->allowedScopes[$scope] = true;
        }
    }

    public function getAllowedScopes(): array
    {
        return array_keys($this->allowedScopes);
    }

    public function isAllowed(string $scope): bool
    {
        return isset($this->allowedScopes[$scope]);
    }

    public function parse(): \Generator
    {
        yield self::PROTECTION;
    }
}
