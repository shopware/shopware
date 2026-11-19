<?php declare(strict_types=1);

namespace Shopware\Core\Framework\PasswordHasher;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Uses PHP's native `password_hash()` and verifies every hash `password_verify()` understands,
 * so hashes of a previously configured native algorithm keep working and are upgraded on login.
 *
 * @final
 */
#[Package('framework')]
class NativePasswordHasher extends AbstractPasswordHasher
{
    final public const ALGORITHM_DEFAULT = 'default';
    final public const ALGORITHM_BCRYPT = 'bcrypt';
    final public const ALGORITHM_ARGON2I = 'argon2i';
    final public const ALGORITHM_ARGON2ID = 'argon2id';

    final public const ALGORITHMS = [
        self::ALGORITHM_DEFAULT,
        self::ALGORITHM_BCRYPT,
        self::ALGORITHM_ARGON2I,
        self::ALGORITHM_ARGON2ID,
    ];

    private readonly string $algorithm;

    /**
     * @param array<string, int> $options options passed to `password_hash()`, e.g. `cost` for bcrypt or `memory_cost`, `time_cost` and `threads` for argon2
     */
    public function __construct(
        string $algorithm = self::ALGORITHM_DEFAULT,
        private readonly array $options = [],
    ) {
        $this->algorithm = match ($algorithm) {
            self::ALGORITHM_DEFAULT => \PASSWORD_DEFAULT,
            self::ALGORITHM_BCRYPT => \PASSWORD_BCRYPT,
            default => $algorithm,
        };

        if (!\in_array($this->algorithm, password_algos(), true)) {
            throw PasswordHasherException::unsupportedAlgorithm($algorithm, password_algos());
        }
    }

    public function getDecorated(): AbstractPasswordHasher
    {
        throw new DecorationPatternException(self::class);
    }

    public function hash(#[\SensitiveParameter] string $plainPassword): string
    {
        return password_hash($plainPassword, $this->algorithm, $this->options);
    }

    public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return password_needs_rehash($hashedPassword, $this->algorithm, $this->options);
    }

    public function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }
}
