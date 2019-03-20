<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("ALL")
 */
class Event
{
    /**
     * @var string
     */
    private $eventClass;

    public function __construct(array $values)
    {
        $this->eventClass = $values['value'];
    }

    public function getEventClass(): string
    {
        return $this->eventClass;
    }
}
