<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Password\LegacyEncoder;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface LegacyEncoderInterface
{
    public function getName(): string;

    public function isPasswordValid(string $password, string $hash): bool;
}
