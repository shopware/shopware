<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Symfony\Contracts\EventDispatcher\Event;

class BeforeVersionMergeEvent extends Event
{
    private array $writes;

    public function __construct(array &$writes)
    {
        $this->writes = &$writes;
    }

    public function &getWrites(): array
    {
        return $this->writes;
    }
}
