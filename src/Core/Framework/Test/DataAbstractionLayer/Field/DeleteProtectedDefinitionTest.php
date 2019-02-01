<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\InsufficientDeletePermissionException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DeleteProtectedDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class DeleteProtectedDefinitionTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` binary(16) NOT NULL,
  `relation_id` binary(16) NULL,
  `protected` varchar(255) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;

        $this->connection->executeUpdate($nullableTable);
    }

    public function testDeleteWithoutPermission(): void
    {
        $id = Uuid::uuid4()->getHex();
        $context = $this->createWriteContext();
        $context->getContext()->getDeleteProtection()->allow('random_key');

        $ex = null;
        try {
            $this->getWriter()->delete(DeleteProtectedDefinition::class, [$id], $context);
        } catch (InsufficientDeletePermissionException $ex) {
        }

        static::assertInstanceOf(InsufficientDeletePermissionException::class, $ex);
        static::assertEquals($ex->getMessage(), sprintf('Cannot delete entity. Missing permission: %s', DeleteProtectedDefinition::getDeleteProtectionKey()));
    }

    public function testDeleteWithoutAnyPermission(): void
    {
        $id = Uuid::uuid4()->getHex();
        $context = $this->createWriteContext();

        $ex = null;
        try {
            $this->getWriter()->delete(DeleteProtectedDefinition::class, [$id], $context);
        } catch (InsufficientDeletePermissionException $ex) {
        }

        static::assertInstanceOf(InsufficientDeletePermissionException::class, $ex);
        static::assertEquals($ex->getMessage(), sprintf('Cannot delete entity. Missing permission: %s', DeleteProtectedDefinition::getDeleteProtectionKey()));
    }

    public function testWriteWithPermission(): void
    {
        $id = Uuid::uuid4()->getHex();
        $context = $this->createWriteContext();
        $context->getContext()->getDeleteProtection()->allow(DeleteProtectedDefinition::getDeleteProtectionKey());

        $result = $this->getWriter()->delete(DeleteProtectedDefinition::class, [$id], $context);

        static::assertNotNull($result);
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }
}
