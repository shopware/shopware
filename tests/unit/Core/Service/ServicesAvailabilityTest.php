<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServicesAvailability;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServicesAvailability::class)]
class ServicesAvailabilityTest extends TestCase
{
    #[DataProvider('availabilityProvider')]
    public function testAvailable(string $setting, string $appEnv, bool $expectedAvailable): void
    {
        static::assertSame($expectedAvailable, (new ServicesAvailability($setting, $appEnv))->available());
    }

    public static function availabilityProvider(): \Generator
    {
        yield 'auto turns services on in prod' => [
            ServicesAvailability::AUTO,
            'prod',
            true,
        ];

        yield 'auto keeps services off outside prod' => [
            ServicesAvailability::AUTO,
            'dev',
            false,
        ];

        yield 'an explicit true turns services on outside prod' => [
            'true',
            'dev',
            true,
        ];

        yield 'an explicit false keeps services off in prod' => [
            'false',
            'prod',
            false,
        ];

        yield 'numeric settings are read as booleans' => [
            '0',
            'prod',
            false,
        ];
    }

    public function testSerializesTheDecisionWithTheSettingBehindIt(): void
    {
        static::assertSame(
            '{"enabled":false,"setting":"auto"}',
            json_encode(new ServicesAvailability(ServicesAvailability::AUTO, 'dev')),
        );
    }
}
