<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\InstallerKernel;
use Shopware\Core\Kernel;

/**
 * @internal
 * @covers \Shopware\Core\Installer\InstallerKernel
 */
class InstallerKernelTest extends TestCase
{
    public function testItParsesVersion(): void
    {
        $kernel = new InstallerKernel('test', false);

        $kernel->boot();

        static::assertSame(
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $kernel->getContainer()->getParameter('kernel.shopware_version')
        );

        static::assertSame(
            '00000000000000000000000000000000',
            $kernel->getContainer()->getParameter('kernel.shopware_version_revision')
        );
    }
}
