<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * This event is dispatched during the version merge process and allows listeners to manipulate the writes array before they are applied.
 *
 * @phpstan-type WriteOperation array<string, array<int, mixed>>
 * @phpstan-type Writes array{
 *     insert: WriteOperation,
 *     update: WriteOperation,
 *     delete: WriteOperation
 * }
 */
#[Package('framework')]
class BeforeVersionMergeEvent extends Event
{
    /**
     * @param Writes $writes
     */
    public function __construct(public array $writes)
    {
    }

    /**
     * @return Writes
     */
    public function filterWrites(callable $callback): array
    {
        $filtered = array_filter($this->writes, $callback);

        /** @var Writes $filtered */
        return $filtered;
    }
}
