<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\LockValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class LockValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityWriterInterface $entityWriter;

    private EntityDefinition $testDefinition;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->testDefinition = $this->getContainer()->get(TestDefinition::class);
        $this->entityWriter = $this->getContainer()->get(EntityWriter::class);

        $table = <<<EOF
DROP TABLE IF EXISTS _test_lock;
CREATE TABLE `_test_lock` (
  `id` binary(16) NOT NULL,
  `description` varchar(255) NULL,
  `locked` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`id`)
);

DROP TABLE IF EXISTS _test_lock_translation;
CREATE TABLE `_test_lock_translation` (
  `_test_lock_id` binary(16) NOT NULL,
  `language_id` binary(16) NOT NULL,
  `name` varchar(255) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`_test_lock_id`, `language_id`)
);
EOF;
        $this->connection->rollBack();
        $this->connection->executeStatement($table);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `_test_lock`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `_test_lock_translation`');
        $this->connection->beginTransaction();

        parent::tearDown();
    }

    public function testCreateShouldNotBeBlocked(): void
    {
        $data = [
            'id' => Uuid::randomHex(),
            'description' => 'foo',
        ];

        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());

        static::assertCount(1, $r);
    }

    public function testCreateWithLockShouldNotBeWritten(): void
    {
        $data = [
            'id' => Uuid::randomHex(),
            'description' => 'foo',
            'locked' => true,
        ];

        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());

        static::assertCount(1, $r);

        $isLocked = (bool) $this->connection->fetchOne('SELECT `locked` FROM `_test_lock` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        static::assertFalse($isLocked);
    }

    public function testUpdateOnUnlockedShouldPass(): void
    {
        $data = ['id' => Uuid::randomHex(), 'description' => 'foo'];
        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(1, $r);

        $data = ['id' => $data['id'], 'description' => 'bar'];
        $r = $this->entityWriter->update($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(1, $r);

        $description = $this->connection->fetchOne('SELECT `description` FROM `_test_lock` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        static::assertEquals('bar', $description);
    }

    public function testUpdateTranslationOnUnlockedShouldPass(): void
    {
        $data = ['id' => Uuid::randomHex(), 'description' => 'foo', 'name' => 'shop'];
        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(2, $r);

        $data = ['id' => $data['id'], 'description' => 'bar', 'name' => 'ware'];
        $r = $this->entityWriter->update($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(2, $r);

        $description = $this->connection->fetchOne('SELECT `description` FROM `_test_lock` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);
        $name = $this->connection->fetchOne('SELECT `name` FROM `_test_lock_translation` WHERE `_test_lock_id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        static::assertEquals('bar', $description);
        static::assertEquals('ware', $name);
    }

    public function testUpdateOnLockedShouldBePrevented(): void
    {
        $data = ['id' => Uuid::randomHex(), 'description' => 'foo'];
        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(1, $r);

        $this->connection->executeStatement('UPDATE `_test_lock` SET `locked` = 1 WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        $exception = null;

        try {
            $data = ['id' => $data['id'], 'description' => 'bar'];
            $this->entityWriter->update($this->testDefinition, [$data], $this->getWriteContext());
        } catch (WriteException $exception) {
        }

        $this->assertLockException($exception);
    }

    public function testDeleteOnLockedShouldBePrevented(): void
    {
        $data = ['id' => Uuid::randomHex(), 'description' => 'foo'];
        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(1, $r);

        $this->connection->executeStatement('UPDATE `_test_lock` SET `locked` = 1 WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        $exception = null;

        try {
            $data = ['id' => $data['id']];
            $this->entityWriter->delete($this->testDefinition, [$data], $this->getWriteContext());
        } catch (WriteException $exception) {
        }

        $this->assertLockException($exception);
    }

    public function testUpdateOnTranslationShouldBePrevented(): void
    {
        $data = ['id' => Uuid::randomHex(), 'description' => 'foo', 'name' => 'shop'];
        $r = $this->entityWriter->insert($this->testDefinition, [$data], $this->getWriteContext());
        static::assertCount(2, $r);

        $this->connection->executeStatement('UPDATE `_test_lock` SET `locked` = 1 WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($data['id'])]);

        $exception = null;

        try {
            $data = ['id' => $data['id'], 'name' => 'ware'];
            $this->entityWriter->update($this->testDefinition, [$data], $this->getWriteContext());
        } catch (WriteException $exception) {
        }

        $this->assertLockException($exception);
    }

    private function getWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    private function assertLockException(\Exception $exception): void
    {
        static::assertInstanceOf(WriteException::class, $exception);

        static::assertCount(1, $exception->getExceptions());

        /** @var WriteConstraintViolationException $violationException */
        $violationException = $exception->getExceptions()[0];
        $violation = $violationException->getViolations()->findByCodes(LockValidator::VIOLATION_LOCKED);

        static::assertNotNull($violation);
        static::assertEquals(LockValidator::VIOLATION_LOCKED, $violation->get(0)->getCode());
    }
}
