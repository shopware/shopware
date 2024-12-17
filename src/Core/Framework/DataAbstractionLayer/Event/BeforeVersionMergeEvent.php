<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class BeforeVersionMergeEvent extends Event
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $writes;

    /**
     * @param array<string, array<string, mixed>> $writes
     */
    public function __construct(array &$writes)
    {
        $this->writes = &$writes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function &getWrites(): array
    {
        return $this->writes;
    }
}
