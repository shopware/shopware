<?php declare(strict_types=1);

namespace Shopware\Core\Content;

use Shopware\Core\Content\Mail\MailerConfigurationCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('core')]
class Content extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('category.xml');
        $loader->load('media.xml');
        $loader->load('media_path.xml');
        $loader->load('product.xml');
        $loader->load('newsletter_recipient.xml');
        $loader->load('rule.xml');
        $loader->load('product_stream.xml');
        $loader->load('product_export.xml');
        $loader->load('property.xml');
        $loader->load('cms.xml');
        $loader->load('mail_template.xml');
        $loader->load('delivery_time.xml');
        $loader->load('import_export.xml');
        $loader->load('contact_form.xml');
        $loader->load('sitemap.xml');
        $loader->load('landing_page.xml');
        $loader->load('flow.xml');

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
