<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\TriggerCollection;

use Shopware\Core\Framework\Migration\Trigger;
use Shopware\Core\Framework\Migration\TriggerDirection;
use Shopware\Core\Framework\Migration\TriggerEvent;
use Shopware\Core\Framework\Migration\TriggerTime;

class BidirectionalTriggerCollection implements TriggerCollection
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $forwardColumn;

    /**
     * @var string
     */
    private $backwardColumn;

    public function __construct(string $name, string $table, string $forwardColumn, string $backwardColumn)
    {
        $this->name = $name;
        $this->table = $table;
        $this->forwardColumn = $forwardColumn;
        $this->backwardColumn = $backwardColumn;
    }

    public function getTrigger(): array
    {
        return [
            new Trigger(
                $this->name . 'BeforeInsertForward',
                TriggerTime::BEFORE,
                TriggerEvent::INSERT,
                TriggerDirection::FORWARD,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->forwardColumn, $this->backwardColumn)
            ),
            new Trigger(
                $this->name . 'BeforeUpdateForward',
                TriggerTime::BEFORE,
                TriggerEvent::UPDATE,
                TriggerDirection::FORWARD,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->forwardColumn, $this->backwardColumn)
            ),
            new Trigger(
                $this->name . 'BeforeInsertBackward',
                TriggerTime::BEFORE,
                TriggerEvent::INSERT,
                TriggerDirection::BACKWARD,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->backwardColumn, $this->forwardColumn)
            ),
            new Trigger(
                $this->name . 'BeforeUpdateBackward',
                TriggerTime::BEFORE,
                TriggerEvent::UPDATE,
                TriggerDirection::BACKWARD,
                $this->table,
                sprintf('SET NEW.`%s` = NEW.`%s`', $this->backwardColumn, $this->forwardColumn)
            ),
        ];
    }
}
