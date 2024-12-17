<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('core')]
class BeforeVersionMergeEvent extends Event
{
    /**
     * @var array{
     *     insert: array<string, array<int, mixed>>,
     *     update: array<string, array<int, mixed>>,
     *     delete: array<string, array<int, mixed>>
     * }
     */
    private array $writes;

    /**
     * @param array{
     *     insert: array<string, array<int, mixed>>,
     *     update: array<string, array<int, mixed>>,
     *     delete: array<string, array<int, mixed>>
     * } $writes
     */
    public function __construct(array &$writes)
    {
        $this->writes = &$writes;
    }

    /**
     * @return array{
     *     insert: array<string, array<int, mixed>>,
     *     update: array<string, array<int, mixed>>,
     *     delete: array<string, array<int, mixed>>
     * }
     */
    public function &getWrites(): array
    {
        return $this->writes;
    }
}
