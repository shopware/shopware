<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\TriggerCollection;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\Trigger;
use Shopware\Core\Framework\Migration\TriggerCollection\BidirectionalTriggerCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class BidirectionalTriggerCollectionTest extends TestCase
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

    public function test_it_triggers_insert_forward()
    {
        $triggers = (new BidirectionalTriggerCollection(
            'testTrigger',
            'migration',
            'message',
            'class'
        ))->getTrigger();

        foreach ($triggers as $trigger) {
            $trigger->add($this->connection, 1);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(4, count($activeTriggers));

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertEquals('testClass', $message);

        foreach ($triggers as $trigger) {
            $trigger->drop($this->connection);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($activeTriggers));

        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }

    public function test_it_triggers_update_forward()
    {
        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $triggers = (new BidirectionalTriggerCollection(
            'testTrigger',
            'migration',
            'message',
            'class'
        ))->getTrigger();

        foreach ($triggers as $trigger) {
            $trigger->add($this->connection, 1);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(4, count($activeTriggers));

        $this->connection->executeQuery('
            UPDATE `migration` SET `class` = "anotherTest" WHERE `class` = "testClass"'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "anotherTest"'
        )->fetchColumn();
        $this->assertEquals('anotherTest', $message);

        foreach ($triggers as $trigger) {
            $trigger->drop($this->connection);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($activeTriggers));

        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "anotherTest"');
    }

    public function test_it_triggers_insert_backward()
    {
        $triggers = (new BidirectionalTriggerCollection(
            'testTrigger',
            'migration',
            'class',
            'message'
        ))->getTrigger();

        foreach ($triggers as $trigger) {
            $trigger->add($this->connection, 1);
        }
        $this->connection->executeQuery(sprintf('
            SET %s = TRUE;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(4, count($activeTriggers));

        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "testClass"'
        )->fetchColumn();
        $this->assertEquals('testClass', $message);

        foreach ($triggers as $trigger) {
            $trigger->drop($this->connection);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($activeTriggers));

        $this->connection->executeQuery(sprintf('
            SET %s = NULL;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));
        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "testClass"');
    }

    public function test_it_triggers_update_backward()
    {
        $this->connection->executeQuery('
            INSERT INTO `migration` (`class`, `creation_timestamp`) VALUES ("testClass", 1)'
        );

        $triggers = (new BidirectionalTriggerCollection(
            'testTrigger',
            'migration',
            'class',
            'message'
        ))->getTrigger();

        foreach ($triggers as $trigger) {
            $trigger->add($this->connection, 1);
        }
        $this->connection->executeQuery(sprintf('
            SET %s = TRUE;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(4, count($activeTriggers));

        $this->connection->executeQuery('
            UPDATE `migration` SET `class` = "anotherTest" WHERE `class` = "testClass"'
        );

        $message = $this->connection->executeQuery('
            SELECT `message` FROM `migration` WHERE `class` = "anotherTest"'
        )->fetchColumn();
        $this->assertEquals('anotherTest', $message);

        foreach ($triggers as $trigger) {
            $trigger->drop($this->connection);
        }

        $activeTriggers = $this->connection->executeQuery('SHOW TRIGGERS')->fetchAll();
        self::assertEquals(0, count($activeTriggers));

        $this->connection->executeQuery(sprintf('
            SET %s = NULL;
        ', sprintf(Trigger::TRIGGER_VARIABLE_FORMAT, 1)));
        $this->connection->executeQuery('DELETE FROM `migration` WHERE `class` = "anotherTest"');
    }
}
