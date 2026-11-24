<?php declare(strict_types=1);

namespace Shopware\Core\Framework\PasswordHasher;

use Shopware\Core\Framework\Log\Package;

/**
 * Hashes and verifies the passwords of one scope, e.g. customers or administration users.
 *
 * Decorate `shopware.password_hasher.customer` or `shopware.password_hasher.admin` to use a custom algorithm.
 * When switching to a new hash format, keep verifying the old format (e.g. by delegating to the decorated hasher)
 * and return `true` from `needsRehash()` for it, so existing hashes are upgraded on the next successful login.
 */
#[Package('framework')]
abstract class AbstractPasswordHasher
{
    abstract public function getDecorated(): AbstractPasswordHasher;

    abstract public function hash(#[\SensitiveParameter] string $plainPassword): string;

    abstract public function verify(string $hashedPassword, #[\SensitiveParameter] string $plainPassword): bool;

    /**
     * Takes as long as verifying a stored hash, but never succeeds.
     * Called when there is no stored hash to verify against, e.g. for an unknown email,
     * so the response time does not reveal whether an account exists.
     */
    abstract public function verifyPlaceholder(#[\SensitiveParameter] string $plainPassword): void;

    /**
     * Returns true if the hash was created with outdated settings or by another algorithm and should be replaced.
     */
    abstract public function needsRehash(string $hashedPassword): bool;

    /**
     * Returns true if the value already is a hash this hasher can verify.
     * Already hashed values are stored as they are, e.g. when importing customers via the Sync API.
     */
    abstract public function isHashed(string $value): bool;
}
