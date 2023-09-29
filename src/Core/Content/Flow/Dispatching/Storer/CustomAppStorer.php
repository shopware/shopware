<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\CustomAppAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class CustomAppStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!($event instanceof CustomAppAware) || isset($stored[CustomAppAware::CUSTOM_DATA]) || empty($event->getCustomAppData())) {
            return $stored;
        }

        foreach ($event->getCustomAppData() as $key => $data) {
            $stored[ScalarValuesAware::STORE_VALUES][$key] = $data;
            $stored[$key] = $data;
        }

        return $stored;
    }

    /**
     * @codeCoverageIgnore
     */
    public function restore(StorableFlow $storable): void
    {
    }
}
