<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class WriteProtection extends EntityProtection
{
    final public const PROTECTION = 'write_protection';

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
