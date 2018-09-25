<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Trigger;
use Shopware\Core\Framework\Migration\TriggerDirection;
use Shopware\Core\Framework\Migration\TriggerEvent;
use Shopware\Core\Framework\Migration\TriggerTime;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TriggerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        $container = self::getKernel()->getContainer();

        $this->connection = $container->get(Connection::class);
    }

    public function test_trigger_forward()
    {
        $trigger = new Trigger(
            'testTrigger',
            TriggerTime::BEFORE,
            TriggerEvent::INSERT,
            TriggerDirection::FORWARD,
            'migration',
            'SET NEW.`message` = NEW.`class`'
        );

        $trigger->add($this->connection, 1);

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertEquals('testClass', $message);

        $trigger->drop($this->connection);

        $triggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($triggers));

        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }

    public function test_trigger_forward_not_triggering()
    {
        $trigger = new Trigger(
            'testTrigger',
            TriggerTime::BEFORE,
            TriggerEvent::INSERT,
            TriggerDirection::FORWARD,
            'migration',
            'SET NEW.`message` = NEW.`class`'
        );

        $trigger->add($this->connection, 1);
        $this->connection->executeQuery(sprintf('
            SET %s = TRUE;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertNull($message);

        $trigger->drop($this->connection);

        $triggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($triggers));

        $this->connection->executeQuery(sprintf('
            SET %s = NULL;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));
        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }

    public function test_trigger_backward()
    {
        $trigger = new Trigger(
            'testTrigger',
            TriggerTime::BEFORE,
            TriggerEvent::INSERT,
            TriggerDirection::BACKWARD,
            'migration',
            'SET NEW.`message` = NEW.`class`'
        );

        $trigger->add($this->connection, 1);
        $this->connection->executeQuery(sprintf('
            SET %s = TRUE;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertEquals('testClass', $message);

        $trigger->drop($this->connection);

        $triggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($triggers));

        $this->connection->executeQuery(sprintf('
            SET %s = NULL;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));
        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }

    public function test_trigger_backward_not_triggering()
    {
        $trigger = new Trigger(
            'testTrigger',
            TriggerTime::BEFORE,
            TriggerEvent::INSERT,
            TriggerDirection::BACKWARD,
            'migration',
            'SET NEW.`message` = NEW.`class`'
        );

        $trigger->add($this->connection, 1);

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertNull($message);

        $trigger->drop($this->connection);

        $triggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($triggers));
        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }
}
