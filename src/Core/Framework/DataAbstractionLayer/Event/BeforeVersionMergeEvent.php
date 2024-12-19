<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @phpstan-type WriteOperation array<string, array<int, mixed>>
 * @phpstan-type Writes array{
 *     insert: WriteOperation,
 *     update: WriteOperation,
 *     delete: WriteOperation
 * }
 */
#[Package('core')]
class BeforeVersionMergeEvent extends Event
{
    /**
     * @var Writes
     */
    private array $writes;

    /**
     * @param Writes $writes
     */
    public function __construct(array &$writes)
    {
        $this->writes = &$writes;
    }

    /**
     * @return Writes
     */
    public function &getWrites(): array
    {
        return $this->writes;
    }
}
