<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\Environment;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerData;
use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EnvironmentHelper::class)]
class EnvironmentHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['SW_TEST_VAR'], $_ENV['SW_TEST_VAR'], $_SERVER['CI'], $_ENV['CI']);
    }

    #[Before]
    #[After]
    public function removeAllTransformers(): void
    {
        EnvironmentHelper::removeAllTransformers();
    }

    #[DataProvider('getVariableProvider')]
    public function testGetVariable(?string $server, ?string $env, ?string $expected): void
    {
        if ($server !== null) {
            $_SERVER['SW_TEST_VAR'] = $server;
        } else {
            unset($_SERVER['SW_TEST_VAR']);
        }

        if ($env !== null) {
            $_ENV['SW_TEST_VAR'] = $env;
        } else {
            unset($_ENV['SW_TEST_VAR']);
        }

        static::assertSame($expected, EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    /**
     * @return \Generator<string, array{server: ?string, env: ?string, expected: ?string}>
     */
    public static function getVariableProvider(): \Generator
    {
        yield 'reads from $_SERVER' => ['server' => 'from_server', 'env' => null, 'expected' => 'from_server'];
        yield 'reads from $_ENV' => ['server' => null, 'env' => 'from_env', 'expected' => 'from_env'];
        yield '$_SERVER takes precedence over $_ENV' => ['server' => 'server_value', 'env' => 'env_value', 'expected' => 'server_value'];
        yield 'returns null when not set' => ['server' => null, 'env' => null, 'expected' => null];
    }

    #[DataProvider('defaultValueProvider')]
    public function testGetVariableReturnsDefaultWhenNotSet(bool|float|int|string $default): void
    {
        unset($_SERVER['SW_TEST_VAR'], $_ENV['SW_TEST_VAR']);

        static::assertSame($default, EnvironmentHelper::getVariable('SW_TEST_VAR', $default));
    }

    /**
     * @return \Generator<string, array{default: bool|float|int|string}>
     */
    public static function defaultValueProvider(): \Generator
    {
        yield 'string default' => ['default' => 'fallback'];
        yield 'integer default' => ['default' => 42];
        yield 'float default' => ['default' => 3.14];
        yield 'boolean true default' => ['default' => true];
        yield 'boolean false default' => ['default' => false];
    }

    #[DataProvider('hasVariableProvider')]
    public function testHasVariable(?string $server, ?string $env, bool $expected): void
    {
        if ($server !== null) {
            $_SERVER['SW_TEST_VAR'] = $server;
        } else {
            unset($_SERVER['SW_TEST_VAR']);
        }

        if ($env !== null) {
            $_ENV['SW_TEST_VAR'] = $env;
        } else {
            unset($_ENV['SW_TEST_VAR']);
        }

        static::assertSame($expected, EnvironmentHelper::hasVariable('SW_TEST_VAR'));
    }

    /**
     * @return \Generator<string, array{server: ?string, env: ?string, expected: bool}>
     */
    public static function hasVariableProvider(): \Generator
    {
        yield 'true when set in $_SERVER' => ['server' => 'anything', 'env' => null, 'expected' => true];
        yield 'true when set in $_ENV' => ['server' => null, 'env' => 'anything', 'expected' => true];
        yield 'false when not set' => ['server' => null, 'env' => null, 'expected' => false];
    }

    #[DataProvider('ciModeProvider')]
    public function testIsCiMode(string $value, bool $expected): void
    {
        $_SERVER['CI'] = $value;
        unset($_ENV['CI']);

        static::assertSame($expected, EnvironmentHelper::isCiMode());
    }

    /**
     * @return \Generator<string, array{value: string, expected: bool}>
     */
    public static function ciModeProvider(): \Generator
    {
        yield 'truthy string 1' => ['value' => '1', 'expected' => true];
        yield 'truthy string true' => ['value' => 'true', 'expected' => true];
        yield 'falsy string 0' => ['value' => '0', 'expected' => false];
        yield 'empty string' => ['value' => '', 'expected' => false];
    }

    public function testIsCiModeReturnsFalseWhenNotSet(): void
    {
        unset($_SERVER['CI'], $_ENV['CI']);

        static::assertFalse(EnvironmentHelper::isCiMode());
    }

    public function testAddTransformerWithHigherPriorityRunsFirst(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        // priority 0 appends _bar, priority 1 (runs first) appends _foo
        EnvironmentHelper::addTransformer(AppendBarTransformer::class, 0);
        EnvironmentHelper::addTransformer(AppendFooTransformer::class, 1);

        // higher priority runs first: hello -> hello_foo -> hello_foo_bar
        static::assertSame('hello_foo_bar', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testAddSameTransformerTwiceAtSamePriorityIsIdempotent(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendBarTransformer::class);
        EnvironmentHelper::addTransformer(AppendBarTransformer::class);

        static::assertSame('hello_bar', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testAddSameTransformerAtDifferentPrioritiesAppliesItTwice(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendBarTransformer::class, 0);
        EnvironmentHelper::addTransformer(AppendBarTransformer::class, 1);

        static::assertSame('hello_bar_bar', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testRemoveTransformerIsNoopWhenNotRegisteredAtGivenPriority(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendBarTransformer::class, 0);
        EnvironmentHelper::removeTransformer(AppendBarTransformer::class, 1); // registered at 0, not 1

        static::assertSame('hello_bar', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testRemoveTransformerStopsApplyingIt(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendBarTransformer::class);
        EnvironmentHelper::removeTransformer(AppendBarTransformer::class);

        static::assertSame('hello', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testRemoveTransformerAtSpecificPriorityOnlyRemovesThatPriority(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendFooTransformer::class, -1);
        EnvironmentHelper::addTransformer(AppendBarTransformer::class, 0);
        EnvironmentHelper::addTransformer(AppendFooTransformer::class, 1);
        EnvironmentHelper::addTransformer(AppendFooTransformer::class, 2);

        // priority descending: 2(_foo), 1(_foo), 0(_bar), -1(_foo)
        static::assertSame('hello_foo_foo_bar_foo', EnvironmentHelper::getVariable('SW_TEST_VAR'));

        EnvironmentHelper::removeTransformer(AppendFooTransformer::class, 1);
        static::assertSame('hello_foo_bar_foo', EnvironmentHelper::getVariable('SW_TEST_VAR'));

        EnvironmentHelper::removeTransformer(AppendFooTransformer::class, -1);
        static::assertSame('hello_foo_bar', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testRemoveAllTransformersClearsAll(): void
    {
        $_SERVER['SW_TEST_VAR'] = 'hello';

        EnvironmentHelper::addTransformer(AppendBarTransformer::class);
        EnvironmentHelper::addTransformer(AppendFooTransformer::class, 1);
        EnvironmentHelper::removeAllTransformers();

        static::assertSame('hello', EnvironmentHelper::getVariable('SW_TEST_VAR'));
    }

    public function testTransformerCanAlterDefault(): void
    {
        unset($_SERVER['SW_TEST_VAR'], $_ENV['SW_TEST_VAR']);

        EnvironmentHelper::addTransformer(AppendBarTransformer::class);

        // transformer appends _bar to value; since value is null it is untouched, default is appended
        static::assertSame('fallback_bar', EnvironmentHelper::getVariable('SW_TEST_VAR', 'fallback'));
    }

    public function testAddTransformerRejectsClassNotImplementingInterface(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(\sprintf(
            'Expected class to implement "%s" but got "%s".',
            EnvironmentHelperTransformerInterface::class,
            self::class,
        )));

        EnvironmentHelper::addTransformer(self::class);
    }
}

/**
 * @internal
 */
class AppendBarTransformer implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        if ($data->getValue() !== null) {
            $data->setValue($data->getValue() . '_bar');
        }

        if ($data->getDefault() !== null) {
            $data->setDefault($data->getDefault() . '_bar');
        }
    }
}

/**
 * @internal
 */
class AppendFooTransformer implements EnvironmentHelperTransformerInterface
{
    public static function transform(EnvironmentHelperTransformerData $data): void
    {
        if ($data->getValue() !== null) {
            $data->setValue($data->getValue() . '_foo');
        }

        if ($data->getDefault() !== null) {
            $data->setDefault($data->getDefault() . '_foo');
        }
    }
}
