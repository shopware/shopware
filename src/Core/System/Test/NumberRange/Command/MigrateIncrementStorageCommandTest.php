<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\NumberRange\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Shopware\Core\System\Test\NumberRange\ValueGenerator\IncrementArrayStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class MigrateIncrementStorageCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IncrementSqlStorage $sqlStorage;

    private IncrementArrayStorage $arrayStorage;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->sqlStorage = $this->getContainer()->get(IncrementSqlStorage::class);
        $this->arrayStorage = new IncrementArrayStorage([]);

        $command = new MigrateIncrementStorageCommand(
            new IncrementStorageRegistry(new \ArrayObject(
                [
                    'SQL' => $this->sqlStorage,
                    'Array' => $this->arrayStorage,
                ]
            ), 'SQL')
        );

        $this->tester = new CommandTester($command);
    }

    public function testMigrateWithConfirmation(): void
    {
        $this->sqlStorage->set(Uuid::randomHex(), 10);
        static::assertNotEmpty($this->sqlStorage->list());
        $before = $this->arrayStorage->list();
        static::assertEmpty($before);

        $this->tester->setInputs(['yes']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        $this->tester->assertCommandIsSuccessful();

        $after = $this->arrayStorage->list();
        static::assertNotEmpty($after);
        static::assertEquals($this->sqlStorage->list(), $this->arrayStorage->list());
    }

    public function testMigrateWithUserAbort(): void
    {
        $this->sqlStorage->set(Uuid::randomHex(), 10);
        static::assertNotEmpty($this->sqlStorage->list());
        static::assertEmpty($this->arrayStorage->list());

        $this->tester->setInputs(['no']);
        $this->tester->execute(['from' => 'SQL', 'to' => 'Array']);

        static::assertEquals(Command::FAILURE, $this->tester->getStatusCode());

        static::assertEmpty($this->arrayStorage->list());
    }
}
