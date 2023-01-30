<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("ALL")
 */
#[Package('business-ops')]
class Event
{
    private string $eventClass;

    private ?string $deprecationVersion = null;

    public function __construct(array $values)
    {
        if (\is_array($values['value'])) {
            $this->eventClass = $values['value'][0];
            $this->deprecationVersion = $values['value'][1];

            return;
        }

        $this->eventClass = $values['value'];
    }

    public function getEventClass(): string
    {
        return $this->eventClass;
    }

    public function getDeprecationVersion(): ?string
    {
        return $this->deprecationVersion;
    }
}
