<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CompanyAccountNameFields;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CompanyAccountNameFields::class)]
class CompanyAccountNameFieldsTest extends TestCase
{
    #[DataProvider('configProvider')]
    public function testAreRequired(?bool $show, ?bool $required, bool $expected): void
    {
        static::assertSame($expected, CompanyAccountNameFields::areRequired(
            $this->config($show, $required),
            TestDefaults::SALES_CHANNEL
        ));
    }

    public function testAreVisibleOnlyFollowsTheShowFlag(): void
    {
        static::assertTrue(CompanyAccountNameFields::areVisible($this->config(true, false), TestDefaults::SALES_CHANNEL));
        static::assertFalse(CompanyAccountNameFields::areVisible($this->config(false, true), TestDefaults::SALES_CHANNEL));
    }

    public function testAbsentKeysDefaultToRequired(): void
    {
        $config = new StaticSystemConfigService([TestDefaults::SALES_CHANNEL => []]);

        static::assertTrue(CompanyAccountNameFields::areRequired($config, TestDefaults::SALES_CHANNEL));
        static::assertTrue(CompanyAccountNameFields::areVisible($config, TestDefaults::SALES_CHANNEL));
    }

    /**
     * @return iterable<string, array{bool|null, bool|null, bool}>
     */
    public static function configProvider(): iterable
    {
        yield 'shown and required' => [true, true, true];
        yield 'shown but optional' => [true, false, false];
        yield 'hidden cannot be required' => [false, true, false];
        yield 'hidden and optional' => [false, false, false];
    }

    private function config(?bool $show, ?bool $required): StaticSystemConfigService
    {
        return new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                CompanyAccountNameFields::CONFIG_SHOW => $show,
                CompanyAccountNameFields::CONFIG_REQUIRED => $required,
            ],
        ]);
    }
}
