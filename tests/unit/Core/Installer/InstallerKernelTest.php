<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Installer\Installer;
use Shopware\Core\Installer\InstallerKernel;
use Shopware\Core\TestBootstrapper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/**
 * @internal
 */
#[CoversClass(InstallerKernel::class)]
class InstallerKernelTest extends TestCase
{
    use EnvTestBehaviour;

    public function testItCorrectlyConfiguresTheContainer(): void
    {
        $this->setEnvVars(['COMPOSER_HOME' => null]);

        $kernel = new InstallerKernel('test', false);

        $kernel->boot();

        static::assertTrue($kernel->getContainer()->hasParameter('kernel.shopware_version'));

        // the default revision changes per commit, if it is set we expect that it is correct
        static::assertTrue($kernel->getContainer()->hasParameter('kernel.shopware_version_revision'));

        static::assertEquals(
            [
                'FrameworkBundle' => FrameworkBundle::class,
                'TwigBundle' => TwigBundle::class,
                'Installer' => Installer::class,
            ],
            $kernel->getContainer()->getParameter('kernel.bundles')
        );

        $projectDir = (new TestBootstrapper())->getProjectDir();

        static::assertSame($projectDir, $kernel->getContainer()->getParameter('kernel.project_dir'));
        static::assertSame($projectDir . '/var/cache/composer', EnvironmentHelper::getVariable('COMPOSER_HOME'));
    }
}
