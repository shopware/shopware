<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\Installer;

use PHPUnit\Framework\Attributes\TestDox;
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
class InstallerKernelTest extends TestCase
{
    use EnvTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnvVars(['COMPOSER_HOME' => null]);
    }

    #[TestDox('boot configures container with shopware version and bundles')]
    public function testItCorrectlyConfiguresTheContainer(): void
    {
        $kernel = new InstallerKernel('test', false);
        $kernel->boot();
        static::assertTrue($kernel->getContainer()->hasParameter('kernel.shopware_version'));

        // the default revision changes per commit, if it is set we expect that it is correct
        static::assertTrue($kernel->getContainer()->hasParameter('kernel.shopware_version_revision'));

        static::assertSame(
            [
                'FrameworkBundle' => FrameworkBundle::class,
                'TwigBundle' => TwigBundle::class,
                'Installer' => Installer::class,
            ],
            $kernel->getContainer()->getParameter('kernel.bundles')
        );
    }

    #[TestDox('boot sets project dir and COMPOSER_HOME fallback')]
    public function testItCorrectlyConfiguresProjectDir(): void
    {
        $kernel = new InstallerKernel('test', false);
        $kernel->boot();
        $projectDir = (new TestBootstrapper())->getProjectDir();

        static::assertSame($projectDir, $kernel->getContainer()->getParameter('kernel.project_dir'));
        static::assertSame($projectDir . '/var/cache/composer', EnvironmentHelper::getVariable('COMPOSER_HOME'));
    }
}
