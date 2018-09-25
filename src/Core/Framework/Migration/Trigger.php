<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

class Trigger
{
    const TRIGGER_VARIABLE_FORMAT = '@MIGRATION_%s_IS_ACTIVE';

    /** @var string */
    protected $name;

    /** @var string */
    protected $time;

    /** @var string */
    protected $event;

    /** @var string */
    protected $direction;

    /** @var string */
    protected $table;

    /** @var string */
    protected $onTrigger;

    public function __construct(string $name, string $time, string $event, string $direction, string $table, string $onTrigger)
    {
        $this->name = $name;
        $this->time = $time;
        $this->event = $event;
        $this->direction = $direction;
        $this->table = $table;
        $this->onTrigger = $onTrigger;
    }

    public function add(Connection $connection, int $migrationTimeStamp): void
    {
        $connection->executeQuery(sprintf('
        DROP TRIGGER IF EXISTS %s;
        CREATE TRIGGER %s
        %s %s ON `%s` FOR EACH ROW
        thisTrigger: BEGIN
            IF (%s %s)
            THEN 
                LEAVE thisTrigger;
            END IF;
            
            %s;
        END;      
        ',
            $this->name,
            $this->name,
            $this->time,
            $this->event,
            $this->table,
            sprintf(self::TRIGGER_VARIABLE_FORMAT, $migrationTimeStamp),
            $this->direction === TriggerDirection::BACKWARD ? 'IS NULL' : '',
            $this->onTrigger
        ));
    }

    public function drop(Connection $connection): void
    {
        $connection->executeQuery(sprintf('
        DROP TRIGGER IF EXISTS %s
        ', $this->name
        ));
    }
}
