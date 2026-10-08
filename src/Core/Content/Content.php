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

        $phpLoader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $phpLoader->load('breadcrumb.php');
        $phpLoader->load('category.php');
        $phpLoader->load('cookie.php');
        $phpLoader->load('media.php');
        $phpLoader->load('media_path.php');
        $phpLoader->load('product.php');
        $phpLoader->load('newsletter_recipient.php');
        $phpLoader->load('rule.php');
        $phpLoader->load('product_stream.php');
        $phpLoader->load('product_export.php');
        $phpLoader->load('property.php');
        $phpLoader->load('cms.php');
        $phpLoader->load('mail.php');
        $phpLoader->load('mail_template.php');
        $phpLoader->load('delivery_time.php');
        $phpLoader->load('import_export.php');
        $phpLoader->load('contact_form.php');
        $phpLoader->load('revocation_request_form.php');
        $phpLoader->load('sitemap.php');
        $phpLoader->load('landing_page.php');
        $phpLoader->load('flow.php');
        $phpLoader->load('measurement_system.php');
        $phpLoader->load('legal_guarantee_notice.php');
        $phpLoader->load('shared.php');

        $phpLoader->load('product_export_tracking.php');

        if ($container->getParameter('kernel.environment') === 'test') {
            $phpLoader->load('media_test.php');
        }

        $container->addCompilerPass(new MailerConfigurationCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->addCompilerPass(new ThumbnailProcessorCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
