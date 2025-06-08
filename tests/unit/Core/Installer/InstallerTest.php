<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Installer;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(Installer::class)]
class InstallerTest extends TestCase
{
    public function testBuild(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $installer = new Installer();
        $installer->build($container);

        static::assertSame(
            [
                'de' => 'de-DE',
                'en' => 'en-GB',
                'us' => 'en-US',
                'cs' => 'cs-CZ',
                'es' => 'es-ES',
                'fr' => 'fr-FR',
                'it' => 'it-IT',
                'nl' => 'nl-NL',
                'pl' => 'pl-PL',
                'pt' => 'pt-PT',
                'sv' => 'sv-SE',
                'da' => 'da-DK',
                'nb' => 'nb-NO',
            ],
            $container->getParameter('shopware.installer.supportedLanguages')
        );
    }
}
