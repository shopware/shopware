<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Feature;
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
        $customFields = null;

        // Prefer Resources/config/custom-fields.xml file over inline manifest definition
        if ($context->appFilesystem->hasFile('Resources', 'config', 'custom-fields.xml')) {
            $customFields = CustomFieldXmlLoader::load(
                $context->appFilesystem->path('Resources', 'config', 'custom-fields.xml')
            );
        } elseif ($context->manifest->getCustomFields() !== null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Defining custom fields inline in manifest.xml is deprecated, use Resources/config/custom-fields.xml instead.');

            $customFields = $context->manifest->getCustomFields();
        }

        $this->customFieldSetPersister->sync(
            $customFields ?? CustomFields::fromArray([]),
            $context->app->getId(),
            $context->app->getName(),
            $context->context
        );
    }
}
