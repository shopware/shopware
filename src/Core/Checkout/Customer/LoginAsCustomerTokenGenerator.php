<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Exception\InvalidLoginAsCustomerTokenException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LoginAsCustomerTokenGenerator
{
    private string $appSecret;

    /**
     * @internal
     */
    public function __construct(string $appSecret)
    {
        $this->appSecret = $appSecret;
    }

    public function generate(string $salesChannelId, string $customerId): string
    {
        $tokenData = [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
        ];

        $data = json_encode($tokenData);

        if ($data === false) {
            throw InvalidLoginAsCustomerTokenException::invalidToken($salesChannelId . ':' . $customerId);
        }

        return hash_hmac('sha256', $data, $this->appSecret);
    }

    public function validate(string $givenToken, string $salesChannelId, string $customerId): void
    {
        $expectedToken = $this->generate($salesChannelId, $customerId);

        if (!hash_equals($expectedToken, $givenToken)) {
            throw CustomerException::invalidToken($givenToken);
        }
    }
}
