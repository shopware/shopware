<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Context\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGatewayResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppContextGatewayResponse::class)]
class AppContextGatewayResponseTest extends TestCase
{
    public function testResponse(): void
    {
        $commands = [
            ['command' => 'context_register-customer', 'payload' => ['billingAddress' => ['salutationId' => 'salutationId', 'firstName' => 'firstName', 'lastName' => 'lastName', 'street' => 'street', 'zipcode' => 'zipcode', 'city' => 'city', 'countryId' => 'countryId']]],
            ['command' => 'context_change-currency', 'payload' => ['iso' => 'EUR']],
        ];

        $response = new AppContextGatewayResponse($commands);

        static::assertCount(2, $response->getCommands());

        $response->add(['command' => 'context_change-language', 'payload' => ['iso' => 'DE-BY']]);

        static::assertCount(3, $response->getCommands());

        $response->merge([['command' => 'context_change-shipping-address', 'payload' => ['shippingAddressId' => 'shippingAddressId']]]);

        static::assertCount(4, $response->getCommands());

        static::assertSame([
            ['command' => 'context_register-customer', 'payload' => ['billingAddress' => ['salutationId' => 'salutationId', 'firstName' => 'firstName', 'lastName' => 'lastName', 'street' => 'street', 'zipcode' => 'zipcode', 'city' => 'city', 'countryId' => 'countryId']]],
            ['command' => 'context_change-currency', 'payload' => ['iso' => 'EUR']],
            ['command' => 'context_change-language', 'payload' => ['iso' => 'DE-BY']],
            ['command' => 'context_change-shipping-address', 'payload' => ['shippingAddressId' => 'shippingAddressId']],
        ], $response->getCommands());
    }
}
