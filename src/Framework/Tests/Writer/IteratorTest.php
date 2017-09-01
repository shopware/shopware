<?php declare(strict_types=1);

namespace Shopware\Framework\Tests\Writer;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Write\Generator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IteratorTest extends KernelTestCase
{
    const UUID = 'AA-BB-CC';

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');

        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function test_exec_gen()
    {
        (new Generator(self::$kernel->getContainer()))->generateAll();
        $this->assertTrue(true);
    }
}



