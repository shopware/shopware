<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Password\LegacyEncoder;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;

#[Package('checkout')]
class Md5 implements LegacyEncoderInterface
{
    public function getName(): string
    {
        return 'Md5';
    }

    public function isPasswordValid(string $password, string $hash): bool
    {
        if (mb_strpos($hash, ':') === false) {
            return hash_equals($hash, Hasher::hash($password, 'md5'));
        }
        [$md5, $salt] = explode(':', $hash);

        return hash_equals($md5, Hasher::hash($password . $salt, 'md5'));
    }
}
