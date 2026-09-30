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
            'breadcrumb.php',
            'category.php',
            'cookie.php',
            'media.php',
            'media_path.php',
            'product.php',
            'newsletter_recipient.php',
            'rule.php',
            'product_stream.php',
            'product_export.php',
            'property.php',
            'cms.php',
            'mail.php',
            'mail_template.php',
            'delivery_time.php',
            'import_export.php',
            'contact_form.php',
            'revocation_request_form.php',
            'sitemap.php',
            'landing_page.php',
            'flow.php',
            'measurement_system.php',
            'legal_guarantee_notice.php',
            'shared.php',
            'product_export_tracking.php',
            'media_test.php',
            'installed.json',
            'MailerConfigurationCompilerPass.php',
            'ThumbnailProcessorCompilerPass.php',
        ];

        static::assertSame($expectedResources, $resourceFiles);
    }
}
