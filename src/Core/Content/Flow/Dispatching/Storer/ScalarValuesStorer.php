<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\SensitiveMailAware;
use Shopware\Core\Framework\Log\Package;

#[Package('after-sales')]
class ScalarValuesStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof ScalarValuesAware && !$event instanceof SensitiveMailAware) {
            return $stored;
        }

        if (isset($stored[ScalarValuesAware::STORE_VALUES])) {
            throw new \RuntimeException('Can not store generic values twice.');
        }

        $values = $event instanceof ScalarValuesAware ? $event->getValues() : [];
        if ($event instanceof SensitiveMailAware) {
            $values['sensitive'] = true;
        }
        $stored[ScalarValuesAware::STORE_VALUES] = $values;

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(ScalarValuesAware::STORE_VALUES)) {
            return;
        }

        $values = $storable->getStore(ScalarValuesAware::STORE_VALUES);
        foreach ($values as $key => $value) {
            $storable->setData($key, $value);
        }
    }
}
