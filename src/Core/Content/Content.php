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
        $phpLoader->load('category.php');
        $phpLoader->load('cookie.php');
        $phpLoader->load('media.php');
        $phpLoader->load('media_path.php');
        $loader->load('product.xml');
        $loader->load('newsletter_recipient.xml');
        $loader->load('rule.xml');
        $loader->load('product_stream.xml');
        $loader->load('product_export.xml');
        $loader->load('property.xml');
        $phpLoader->load('cms.php');
        $loader->load('mail.xml');
        $loader->load('mail_template.xml');
        $phpLoader->load('delivery_time.php');
        $loader->load('import_export.xml');
        $phpLoader->load('contact_form.php');
        $loader->load('revocation_request_form.xml');
        $phpLoader->load('sitemap.php');
        $phpLoader->load('landing_page.php');
        $loader->load('flow.xml');
        $loader->load('measurement_system.xml');
        $loader->load('shared.xml');

        $phpLoader->load('product_export_tracking.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('media_test.php');
        }

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new ThumbnailProcessorCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
