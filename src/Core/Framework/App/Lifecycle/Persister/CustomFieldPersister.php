<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\CustomFieldXmlLoader;
use Shopware\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class CustomFieldPersister implements PersisterInterface
{
    public function __construct(
        private readonly CustomFieldSetPersister $customFieldSetPersister,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $customFields = null;

        // Prefer Resources/custom-fields.xml file over inline manifest definition
        if ($context->appFilesystem->hasFile('Resources', 'custom-fields.xml')) {
            $customFields = CustomFieldXmlLoader::load(
                $context->appFilesystem->path('Resources', 'custom-fields.xml')
            );
        } elseif ($context->manifest->getCustomFields() !== null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Defining custom fields inline in manifest.xml is deprecated, use Resources/custom-fields.xml instead.');

            $customFields = $context->manifest->getCustomFields();
        }

        $this->customFieldSetPersister->sync(
            $customFields ?? CustomFields::fromArray([]),
            $context->app->getId(),
            $context->context
        );
    }
}
