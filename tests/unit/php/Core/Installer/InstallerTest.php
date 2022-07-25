<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Installer;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Installer
 */
class InstallerTest extends TestCase
{
    public function testBuild(): void
    {
        $container = new ContainerBuilder();

        $installer = new Installer();
        $installer->build($container);

        static::assertSame(
            [
                'de',
                'en',
                'cs',
                'es',
                'fr',
                'it',
                'nl',
                'pl',
                'pt',
                'sv',
                'da',
            ],
            $container->getParameter('shopware.installer.supportedLanguages')
        );
    }
}
