<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\CustomFieldXmlLoader;
use Shopware\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CustomFieldLifecycleHandler extends AbstractLifecycleHandler
{
    public function __construct(
        private readonly CustomFieldSetPersister $customFieldSetPersister,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    private function persist(AppPersistContext $context): void
    {
        $customFields = $context->manifest->getCustomFields();

        // The preferred Resources/config/custom-fields.xml takes precedence over the inline manifest definition
        if ($context->appFilesystem->hasFile('Resources', 'config', 'custom-fields.xml')) {
            if ($customFields !== null) {
                $this->logger->warning(
                    'App "{appName}" defines custom fields in both manifest.xml and Resources/config/custom-fields.xml. The inline definition in manifest.xml is ignored.',
                    ['appName' => $context->app->getName()]
                );
            }

            $customFields = CustomFieldXmlLoader::load(
                $context->appFilesystem->path('Resources', 'config', 'custom-fields.xml')
            );
        }

        $this->customFieldSetPersister->sync(
            $customFields ?? CustomFields::fromArray([]),
            $context->app->getId(),
            $context->app->getName(),
            $context->context,
            $context->defaultLocale
        );
    }
}
