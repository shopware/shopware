<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Exception\InvalidLoginAsCustomerTokenException;

class LoginAsCustomerTokenGenerator
{
    private string $appSecret;

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

        return hash_hmac('sha1', json_encode($tokenData), $this->appSecret);
    }

    public function validate(string $givenToken, string $salesChannelId, string $customerId): void
    {
        $expectedToken = $this->generate($salesChannelId, $customerId);

        if (!hash_equals($expectedToken, $givenToken)) {
            throw new InvalidLoginAsCustomerTokenException($givenToken);
        }
    }
}
