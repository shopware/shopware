<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\TriggerCollection;

use Shopware\Core\Framework\Migration\Trigger;
use Shopware\Core\Framework\Migration\TriggerEvent;
use Shopware\Core\Framework\Migration\TriggerTime;

class UnidirectionalTriggerCollection implements TriggerCollection
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $direction;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $newColumn;

    /**
     * @var string
     */
    private $baseColumn;

    public function __construct(string $name, string $direction, string $table, string $newColumn, string $baseColumn)
    {
        $this->name = $name;
        $this->direction = $direction;
        $this->table = $table;
        $this->newColumn = $newColumn;
        $this->baseColumn = $baseColumn;
    }

    public function getTrigger(): array
    {
        return [
            new Trigger(
                $this->name . 'BeforeInsert',
                TriggerTime::BEFORE,
                TriggerEvent::INSERT,
                $this->direction,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->newColumn, $this->baseColumn)
            ),
            new Trigger(
                $this->name . 'BeforeUpdate',
                TriggerTime::BEFORE,
                TriggerEvent::UPDATE,
                $this->direction,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->newColumn, $this->baseColumn)
            ),
        ];
    }
}
