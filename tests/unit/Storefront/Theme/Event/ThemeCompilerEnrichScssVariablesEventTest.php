<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeCompilerEnrichScssVariablesEvent::class)]
class ThemeCompilerEnrichScssVariablesEventTest extends TestCase
{
    #[DataProvider('addVariableProvider')]
    #[TestDox('addVariable stores $_dataName')]
    public function testAddVariable(bool $sanitize, string $value, string $expected): void
    {
        $event = new ThemeCompilerEnrichScssVariablesEvent([], 'sales-channel-id', Context::createDefaultContext());

        $event->addVariable('sw-color-brand-primary', $value, $sanitize);

        static::assertSame(['sw-color-brand-primary' => $expected], $event->getVariables());
    }

    public static function addVariableProvider(): \Generator
    {
        yield 'the raw value' => ['sanitize' => false, 'value' => '#0042a0', 'expected' => '#0042a0'];
        yield 'a quoted value with escaped quotes' => ['sanitize' => true, 'value' => 'URW Din\', sans-serif', 'expected' => '\'URW Din\\\', sans-serif\''];
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $context = Context::createDefaultContext();
        $event = new ThemeCompilerEnrichScssVariablesEvent(['sw-font' => 'serif'], 'sales-channel-id', $context);

        static::assertSame(['sw-font' => 'serif'], $event->getVariables());
        static::assertSame('sales-channel-id', $event->getSalesChannelId());
        static::assertSame($context, $event->getContext());
    }
}
