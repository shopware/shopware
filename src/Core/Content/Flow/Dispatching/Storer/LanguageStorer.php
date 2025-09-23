<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\LanguageAware;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class LanguageStorer extends FlowStorer
{
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(LanguageAware::LANGUAGE_ID, (new ScalarValueType(ScalarValueType::TYPE_STRING))->setNullable());
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof LanguageAware) {
            return $stored;
        }

        $stored[LanguageAware::LANGUAGE_ID] = $event->getLanguageId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(LanguageAware::LANGUAGE_ID)) {
            return;
        }

        $storable->setData(LanguageAware::LANGUAGE_ID, $storable->getStore(LanguageAware::LANGUAGE_ID));
    }
}
