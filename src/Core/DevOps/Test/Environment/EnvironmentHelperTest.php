<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Test\Environment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\DevOps\Environment\EnvironmentHelperTransformerInterface;
use Shopware\Core\DevOps\Test\Environment\_fixtures\EnvironmentHelperTransformer;
use Shopware\Core\DevOps\Test\Environment\_fixtures\EnvironmentHelperTransformer2;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class EnvironmentHelperTest extends TestCase
{
    protected function tearDown(): void
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

    public function testVariableTransformerVariableChangeWorks(): void
    {
        $_SERVER['foo'] = 'foo';
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);

        static::assertEquals('foo bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testAddingMultipleTransformersWorks(): void
    {
        $_SERVER['foo'] = 'foo';
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer2::class, 1);

        static::assertEquals('foo baz bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testTransformerPriorityIsCorrectAfterModifications(): void
    {
        $_SERVER['foo'] = 'foo';

        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer2::class, -1);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer2::class, 1);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer2::class, 2);

        static::assertEquals('foo baz baz bar baz', EnvironmentHelper::getVariable('foo'));

        EnvironmentHelper::removeTransformer(EnvironmentHelperTransformer2::class, 1);
        static::assertEquals('foo baz bar baz', EnvironmentHelper::getVariable('foo'));

        EnvironmentHelper::removeTransformer(EnvironmentHelperTransformer2::class, -1);
        static::assertEquals('foo baz bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testSameTransformerIsOnlyAddedOncePerPriority(): void
    {
        $_SERVER['foo'] = 'foo';
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);

        static::assertEquals('foo bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testSameTransformerIsAddedMultipleTimesForDifferentPriorities(): void
    {
        $_SERVER['foo'] = 'foo';
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class, 1);

        static::assertEquals('foo bar bar', EnvironmentHelper::getVariable('foo'));
    }

    public function testVariableTransformerDefaultChangeWorks(): void
    {
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);

        static::assertEquals('my baz', EnvironmentHelper::getVariable('foo', 'my'));
    }

    public function testRemovingTransformerWorks(): void
    {
        $_SERVER['foo'] = 'foo';
        EnvironmentHelper::addTransformer(EnvironmentHelperTransformer::class);

        static::assertEquals('foo bar', EnvironmentHelper::getVariable('foo'));

        EnvironmentHelper::removeTransformer(EnvironmentHelperTransformer::class);
        static::assertEquals('foo', EnvironmentHelper::getVariable('foo'));
    }

    public function testAddingInvalidClassFails(): void
    {
        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage(
            sprintf(
                'Expected class to implement "%1$s" but got "%2$s".',
                EnvironmentHelperTransformerInterface::class,
                self::class
            )
        );
        EnvironmentHelper::addTransformer(self::class);
    }

    /**
     * @before
     *
     * @after
     */
    public function removeAllTransformers(): void
    {
        EnvironmentHelper::removeAllTransformers();
    }
}
