<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ContactFormDataAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use ScalarValuesStorer/ScalarValuesAware instead
 */
#[Package('services-settings')]
class ContactFormDataStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (Feature::isActive('v6.6.0.0')) {
            return $stored;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use ScalarValuesStorer instead')
        );

        if (!$event instanceof ContactFormDataAware || isset($stored[ContactFormDataAware::CONTACT_FORM_DATA])) {
            return $stored;
        }

        $stored[ContactFormDataAware::CONTACT_FORM_DATA] = $event->getContactFormData();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (Feature::isActive('v6.6.0.0')) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use ScalarValuesStorer instead')
        );

        if (!$storable->hasStore(ContactFormDataAware::CONTACT_FORM_DATA)) {
            return;
        }

        $storable->setData(ContactFormDataAware::CONTACT_FORM_DATA, $storable->getStore(ContactFormDataAware::CONTACT_FORM_DATA));
    }
}
