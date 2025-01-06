<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Composer\Factory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;

/**
 * @internal
 */
#[CoversClass(Factory::class)]
class FactoryTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCreateComposer(): void
    {
        if (isset($_SERVER['COMPOSER_ROOT_VERSION'])) {
            static::markTestSkipped('This test is not compatible with the COMPOSER_ROOT_VERSION environment variable');
        }

        $composer = Factory::createComposer(__DIR__ . '/../_fixtures/core');

        static::assertSame('shopware/platform', $composer->getPackage()->getName());
        static::assertSame('6.6.9999999-dev', $composer->getPackage()->getVersion());
    }

    public function testCreateComposerWithVersion(): void
    {
        $this->setEnvVars(['COMPOSER_ROOT_VERSION' => '6.4.9999999-dev']);
        $composer = Factory::createComposer(__DIR__ . '/../_fixtures/core');

        static::assertSame('shopware/platform', $composer->getPackage()->getName());
        static::assertSame('6.4.9999999.9999999-dev', $composer->getPackage()->getVersion());
    }
}
