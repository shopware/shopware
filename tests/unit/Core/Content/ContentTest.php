<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Content;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Content::class)]
class ContentTest extends TestCase
{
    public function testBuild(): void
    {
        $content = new Content();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        static::assertEmpty($container->getResources());

        $content->build($container);

        static::assertNotEmpty($container->getResources());

        $resourceFiles = [];
        foreach ($container->getResources() as $resource) {
            static::assertInstanceOf(FileResource::class, $resource);
            $fileName = basename($resource->getResource());
            $resourceFiles[] = basename($fileName, '.xml');
        }

        $expectedResources = [
            'breadcrumb',
            'category',
            'media',
            'media_path',
            'product',
            'newsletter_recipient',
            'rule',
            'product_stream',
            'product_export',
            'property',
            'cms',
            'mail_template',
            'delivery_time',
            'import_export',
            'contact_form',
            'sitemap',
            'landing_page',
            'flow',
            'measurement_system',
            'installed.json',
            'MailerConfigurationCompilerPass.php',
        ];

        static::assertSame($expectedResources, $resourceFiles);
    }
}
