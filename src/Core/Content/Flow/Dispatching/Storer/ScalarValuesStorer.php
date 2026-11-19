<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SensitiveMail;
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
        $sensitive = $this->isSensitiveMail($event);
        if (!$event instanceof ScalarValuesAware && !$sensitive) {
            return $stored;
        }

        if (isset($stored[ScalarValuesAware::STORE_VALUES])) {
            throw FlowException::scalarValuesAlreadyStored();
        }

        $values = $event instanceof ScalarValuesAware ? $event->getValues() : [];
        if ($sensitive) {
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

    private function isSensitiveMail(FlowEventAware $event): bool
    {
        if (!$event instanceof MailAware) {
            return false;
        }

        $class = new \ReflectionClass($event);
        do {
            if ($class->getAttributes(SensitiveMail::class) !== []) {
                return true;
            }
        } while ($class = $class->getParentClass());

        return false;
    }
}
