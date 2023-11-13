<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Password;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LegacyPasswordVerifier
{
    /**
     * @internal
     *
     * @param LegacyEncoderInterface[] $encoder
     */
    public function __construct(private readonly iterable $encoder)
    {
    }

    public function verify(string $password, CustomerEntity $customer): bool
    {
        if (!$customer->getLegacyEncoder() || !$customer->getLegacyPassword()) {
            throw CustomerException::badCredentials();
        }

        foreach ($this->encoder as $encoder) {
            if ($encoder->getName() !== $customer->getLegacyEncoder()) {
                continue;
            }

            return $encoder->isPasswordValid($password, $customer->getLegacyPassword());
        }

        throw CustomerException::legacyPasswordEncoderNotFound($customer->getLegacyEncoder());
    }
}
