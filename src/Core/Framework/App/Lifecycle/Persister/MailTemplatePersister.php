<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Content\MailTemplate\MailTemplateLoader;
use Shopware\Core\Content\MailTemplate\MailTemplateSetPersister;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('after-sales')]
class MailTemplatePersister implements PersisterInterface
{
    public function __construct(
        private readonly MailTemplateSetPersister $mailTemplateSetPersister,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        if (!$context->appFilesystem->has('Resources', 'mail-templates', 'mail-templates.xml')) {
            return;
        }

        $mailTemplates = MailTemplateLoader::loadFromFilesystem(
            $context->appFilesystem,
        );

        $this->mailTemplateSetPersister->sync(
            $mailTemplates,
            $context->context
        );
    }

    public function activate(AppEntity $app, Context $context): void
    {
    }

    public function deactivate(AppEntity $app, Context $context): void
    {
    }
}
