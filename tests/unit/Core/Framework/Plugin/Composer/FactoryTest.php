<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer;

use Composer\Composer;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Composer\Factory;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Plugin\Composer\Factory
 */
class FactoryTest extends TestCase
{
    public function testCreateComposer(): void
    {
        if (isset($_SERVER['COMPOSER_ROOT_VERSION'])) {
            static::markTestSkipped('This test is not compatible with the COMPOSER_ROOT_VERSION environment variable');
        }

        $composer = Factory::createComposer(__DIR__ . '/../_fixtures/core');
        static::assertInstanceOf(Composer::class, $composer);

        static::assertSame('shopware/platform', $composer->getPackage()->getName());
        static::assertSame('6.5.9999999.9999999-dev', $composer->getPackage()->getVersion());
    }

    public function testCreateComposerWithVersion(): void
    {
        $_SERVER['COMPOSER_ROOT_VERSION'] = '6.4.9999999.9999999-dev';
        $composer = Factory::createComposer(__DIR__ . '/../_fixtures/core');
        static::assertInstanceOf(Composer::class, $composer);

        static::assertSame('shopware/platform', $composer->getPackage()->getName());
        static::assertSame('6.4.9999999.9999999-dev', $composer->getPackage()->getVersion());

        unset($_SERVER['COMPOSER_ROOT_VERSION']);
    }
}
