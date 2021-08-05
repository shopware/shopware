<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\Environment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

class EnvironmentHelperTest extends TestCase
{
    public function tearDown(): void
    {
        // to prevent side effects delete test env var after each testcase
        unset($_SERVER['foo'], $_ENV['foo']);
    }

    public function testGetVariableReadsEnvVarFromServerSuperGlobal(): void
    {
        $_SERVER['foo'] = 'bar';
        unset($_ENV['foo']);

        static::assertEquals('bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testGetVariableReadsEnvVarFromEnvSuperGlobal(): void
    {
        $_ENV['foo'] = 'bar';
        unset($_SERVER['foo']);

        static::assertEquals('bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testGetVariableServerHasPrecedence(): void
    {
        $_SERVER['foo'] = 'bar';
        $_ENV['foo'] = 'baz';

        static::assertEquals('bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testGetVariableReturnsNullIfNotSetWithoutDefault(): void
    {
        unset($_SERVER['foo'], $_ENV['foo']);

        static::assertNull(EnvironmentHelper::getVariable('foo'));
    }

    public function testGetVariableReturnsDefault(): void
    {
        unset($_SERVER['foo'], $_ENV['foo']);

        static::assertEquals('default', EnvironmentHelper::getVariable('foo', 'default'));
    }

    public function testHasVariableReturnsTrueForServer(): void
    {
        $_SERVER['foo'] = 'bar';
        unset($_ENV['foo']);

        static::assertTrue(EnvironmentHelper::hasVariable('foo'));
    }

    public function testHasVariableReturnsTrueForEnv(): void
    {
        $_ENV['foo'] = 'bar';
        unset($_SERVER['foo']);

        static::assertTrue(EnvironmentHelper::hasVariable('foo'));
    }

    public function testHasVariableReturnsFalseIfNotSet(): void
    {
        unset($_SERVER['foo'], $_ENV['foo']);

        static::assertFalse(EnvironmentHelper::hasVariable('foo'));
    }
}
