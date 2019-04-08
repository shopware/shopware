<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Password;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\LegacyPasswordEncoderNotFoundException;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;

class LegacyPasswordVerifier
{
    /**
     * @var LegacyEncoderInterface[]
     */
    private $encoder;

    public function __construct(iterable $encoder)
    {
        $this->encoder = $encoder;
    }

    public function verify(string $password, CustomerEntity $customer): bool
    {
        foreach ($this->encoder as $encoder) {
            if ($encoder->getName() !== $customer->getLegacyEncoder()) {
                continue;
            }

            return $encoder->isPasswordValid($password, $customer->getLegacyPassword());
        }

        throw new LegacyPasswordEncoderNotFoundException($customer->getLegacyEncoder());
    }
}
