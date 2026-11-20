<?php declare(strict_types=1);

namespace Shopware\Core\Framework\PasswordHasher;

use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\Log\Package;

/**
 * Provides the configured password hasher per scope, see the `PasswordField::FOR_*` constants.
 *
 * @final
 */
#[Package('framework')]
class PasswordHasherRegistry
{
    /**
     * @internal
     *
     * @param array<string, AbstractPasswordHasher> $hashers keyed by scope
     */
    public function __construct(private readonly array $hashers)
    {
    }

    public function has(string $scope): bool
    {
        return \array_key_exists($scope, $this->hashers);
    }

    /**
     * @param PasswordField::FOR_*|string $scope
     */
    public function get(string $scope): AbstractPasswordHasher
    {
        return $this->hashers[$scope] ?? throw PasswordHasherException::unknownScope($scope, array_keys($this->hashers));
    }
}
