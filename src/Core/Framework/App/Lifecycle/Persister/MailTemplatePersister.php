<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry;
use Shopware\Core\Content\MailTemplate\MailTemplateLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Log\Package;

/**
 * Loads the app's declared mail templates from `Resources/mail-templates/` and registers them with
 * {@see MailTemplateDefaultsRegistry}. No database rows are written — the registry serves the
 * defaults at read time, and the parent `mail_template` / `mail_template_type` rows are created
 * lazily by {@see \Shopware\Core\Content\MailTemplate\MailTemplateMaterializer} when something
 * concretely needs a UUID (merchant override, flow assignment, sales-channel binding).
 *
 * @internal only for use by the app-system
 */
#[Package('after-sales')]
class MailTemplatePersister implements PersisterInterface
{
    public function __construct(
        private readonly MailTemplateDefaultsRegistry $mailTemplateDefaultsRegistry,
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

        $this->mailTemplateDefaultsRegistry->register($mailTemplates);
    }
}
