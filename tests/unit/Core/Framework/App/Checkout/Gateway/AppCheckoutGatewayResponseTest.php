<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Checkout\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGatewayResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(AppCheckoutGatewayResponse::class)]
#[Package('checkout')]
class AppCheckoutGatewayResponseTest extends TestCase
{
    public function testApi(): void
    {
        $commands = [
            ['command' => 'command-1', 'payload' => ['key' => 'value']],
            ['command' => 'command-2', 'payload' => ['foo', 'bar']],
        ];

        $response = new AppCheckoutGatewayResponse($commands);

        static::assertSame($commands, $response->getCommands());

        $response->add(['command' => 'command-3', 'payload' => ['baz']]);

        static::assertSame([
            ['command' => 'command-1', 'payload' => ['key' => 'value']],
            ['command' => 'command-2', 'payload' => ['foo', 'bar']],
            ['command' => 'command-3', 'payload' => ['baz']],
        ], $response->getCommands());

        $response->merge([
            ['command' => 'command-4', 'payload' => ['qux']],
            ['command' => 'command-5', 'payload' => ['quux']],
        ]);

        static::assertSame([
            ['command' => 'command-1', 'payload' => ['key' => 'value']],
            ['command' => 'command-2', 'payload' => ['foo', 'bar']],
            ['command' => 'command-3', 'payload' => ['baz']],
            ['command' => 'command-4', 'payload' => ['qux']],
            ['command' => 'command-5', 'payload' => ['quux']],
        ], $response->getCommands());
    }
}
