<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event as SymfonyBaseEvent;

/**
 * @Annotation
 *
 * @Target("ALL")
 */
#[Package('business-ops')]
class Event
{
    /**
     * @var class-string<SymfonyBaseEvent>
     */
    private string $eventClass;

    private ?string $deprecationVersion = null;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values)
    {
        if (\is_array($values['value'])) {
            $this->eventClass = $values['value'][0];
            $this->deprecationVersion = $values['value'][1];

            return;
        }

        $this->eventClass = $values['value'];
    }

    /**
     * @return class-string<SymfonyBaseEvent>
     */
    public function getEventClass(): string
    {
        return $this->eventClass;
    }

    public function getDeprecationVersion(): ?string
    {
        return $this->deprecationVersion;
    }
}
