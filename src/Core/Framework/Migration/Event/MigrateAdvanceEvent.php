<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrateAdvanceEvent extends Event
{
    public const EVENT_NAME = 'migration.migrate.advance';

    /**
     * @var string
     */
    private $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
