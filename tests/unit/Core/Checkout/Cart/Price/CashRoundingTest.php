<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;

/**
 * @internal
 */
#[CoversClass(CashRounding::class)]
class CashRoundingTest extends TestCase
{
    #[DataProvider('provider_german')]
    public function testGerman(float $price, float $expected): void
    {
        $service = new CashRounding();

        $config = new CashRoundingConfig(2, 0.01, true);

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);
    }

    #[DataProvider('provider_hong_kong')]
    public function testHongKong(float $price, float $expected): void
    {
        $service = new CashRounding();

        $config = new CashRoundingConfig(2, 0.10, true);

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);
    }

    #[DataProvider('provider_denmark')]
    public function testDenmark(float $price, float $expected): void
    {
        $service = new CashRounding();

        $config = new CashRoundingConfig(2, 0.50, true);

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);
    }

    #[DataProvider('provider_italy')]
    public function testItaly(float $price, float $expected): void
    {
        $service = new CashRounding();

        $config = new CashRoundingConfig(2, 0.05, true);

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);
    }

    #[DataProvider('provider_sweden')]
    public function testSweden(float $price, float $expected): void
    {
        $service = new CashRounding();

        $config = new CashRoundingConfig(2, 1.0, true);

        $actual = $service->cashRound($price, $config);
        static::assertEquals($expected, $actual);
    }

    public static function provider_german(): \Generator
    {
        yield '19.990 should be 19.99' => [19.990, 19.99];
        yield '19.991 should be 19.99' => [19.991, 19.99];
        yield '19.994 should be 19.99' => [19.994, 19.99];
        yield '19.995 should be 20.00' => [19.995, 20.00];
        yield '19.999 should be 20.00' => [19.999, 20.00];
    }

    public static function provider_hong_kong(): \Generator
    {
        yield '19.40 should be 19.40' => [19.40, 19.40];
        yield '19.41 should be 19.40' => [19.41, 19.40];
        yield '19.44 should be 19.40' => [19.44, 19.40];
        yield '19.444 should be 19.40' => [19.444, 19.40];
        yield '19.445 should be 19.50' => [19.445, 19.50];
        yield '19.45 should be 19.50' => [19.45, 19.50];
        yield '19.49 should be 19.50' => [19.49, 19.50];
    }

    public static function provider_denmark(): \Generator
    {
        yield '19.01 should be 19.00' => [19.01, 19.00];
        yield '19.24 should be 19.00' => [19.24, 19.00];
        yield '19.244 should be 19.00' => [19.244, 19.00];
        yield '19.245 should be 19.50' => [19.245, 19.50];
        yield '19.25 should be 19.50' => [19.25, 19.50];
        yield '19.49 should be 19.50' => [19.49, 19.50];
        yield '19.50 should be 19.50' => [19.50, 19.50];
        yield '19.51 should be 19.50' => [19.51, 19.50];
        yield '19.74 should be 19.50' => [19.74, 19.50];
        yield '19.744 should be 19.50' => [19.744, 19.50];
        yield '19.745 should be 20.00' => [19.745, 20.00];
        yield '19.75 should be 20.00' => [19.75, 20.00];
        yield '19.99 should be 20.00' => [19.99, 20.00];
    }

    public static function provider_italy(): \Generator
    {
        yield '19.50 should be 19.50' => [19.50, 19.50];
        yield '19.51 should be 19.50' => [19.51, 19.50];
        yield '19.52 should be 19.50' => [19.52, 19.50];
        yield '19.524 should be 19.50' => [19.524, 19.50];
        yield '19.525 should be 19.55' => [19.525, 19.55];
        yield '19.53 should be 19.55' => [19.53, 19.55];
        yield '19.54 should be 19.55' => [19.54, 19.55];
        yield '19.55 should be 19.55' => [19.55, 19.55];
        yield '19.56 should be 19.55' => [19.56, 19.55];
        yield '19.57 should be 19.55' => [19.57, 19.55];
        yield '19.574 should be 19.55' => [19.574, 19.55];
        yield '19.575 should be 19.60' => [19.575, 19.60];
        yield '19.58 should be 19.60' => [19.58, 19.60];
        yield '19.59 should be 19.60' => [19.59, 19.60];
    }

    public static function provider_sweden(): \Generator
    {
        yield '19.00 should be 19.00' => [19.00, 19.00];
        yield '19.01 should be 19.00' => [19.01, 19.00];
        yield '19.49 should be 19.00' => [19.49, 19.00];
        yield '19.491 should be 19.00' => [19.491, 19.00];
        yield '19.495 should be 20.00' => [19.495, 20.00];
        yield '19.50 should be 20.00' => [19.50, 20.00];
        yield '19.99 should be 20.00' => [19.99, 20.00];
    }
}
