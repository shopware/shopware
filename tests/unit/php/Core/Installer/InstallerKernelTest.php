<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Installer;
use Shopware\Core\Installer\InstallerKernel;
use Shopware\Core\TestBootstrapper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/**
 * @internal
 * @covers \Shopware\Core\Installer\InstallerKernel
 */
class InstallerKernelTest extends TestCase
{
    public function testItCorrectlyConfiguresTheContainer(): void
    {
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

        static::assertSame((new TestBootstrapper())->getProjectDir(), $kernel->getContainer()->getParameter('kernel.project_dir'));
    }
}
