<?php declare(strict_types=1);

namespace Shopware\Core\Content;

use Shopware\Core\Content\Mail\MailerConfigurationCompilerPass;
use Shopware\Core\Content\Media\DependencyInjection\ThumbnailProcessorCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('framework')]
class Content extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('breadcrumb.xml');
        $loader->load('category.xml');
        $phpLoader->load('cookie.php');
        $loader->load('media.xml');
        $loader->load('media_path.xml');
        $loader->load('product.xml');
        $phpLoader->load('newsletter_recipient.php');
        $loader->load('rule.xml');
        $loader->load('product_stream.xml');
        $loader->load('product_export.xml');
        $loader->load('property.xml');
        $loader->load('cms.xml');
        $phpLoader->load('mail.php');
        $phpLoader->load('mail_template.php');
        $loader->load('delivery_time.xml');
        $loader->load('import_export.xml');
        $loader->load('contact_form.xml');
        $phpLoader->load('revocation_request_form.php');
        $loader->load('sitemap.xml');
        $loader->load('landing_page.xml');
        $phpLoader->load('flow.php');
        $loader->load('measurement_system.xml');
        $phpLoader->load('shared.php');

        $phpLoader->load('product_export_tracking.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('media_test.xml');
        }

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new ThumbnailProcessorCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
