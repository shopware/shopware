<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TreeUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $this->stopTransactionAfter();

        $this->getContainer()
            ->get(Connection::class)
            ->executeStatement(
                'CREATE TABLE IF NOT EXISTS `test_tree` (
                    `id` BINARY(16) NOT NULL,
                    `version_id` BINARY(16) NOT NULL,
                    `parent_id` BINARY(16) NULL,
                    `parent_version_id` BINARY(16) NULL,
                    `test_level` INT(11) unsigned NOT NULL DEFAULT 1,
                    `test_path` LONGTEXT COLLATE utf8mb4_unicode_ci,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`, `version_id`)
                )'
            );

        $this->getContainer()
            ->get(Connection::class)
            ->executeStatement(
                'CREATE TABLE IF NOT EXISTS `test_tree_without_version` (
                    `id` BINARY(16) NOT NULL,
                    `parent_id` BINARY(16) NULL,
                    `parent_version_id` BINARY(16) NULL,
                    `test_level` INT(11) unsigned NOT NULL DEFAULT 1,
                    `test_path` LONGTEXT COLLATE utf8mb4_unicode_ci,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`)
                )'
            );

        $this->startTransactionBefore();
    }

    protected function tearDown(): void
    {
        $this->stopTransactionAfter();
        $this->getContainer()
            ->get(Connection::class)
            ->executeStatement('DROP TABLE IF EXISTS `test_tree`, `test_tree_without_version`');

        $this->startTransactionBefore();
    }

    public function testTreeUpdate(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $ids = new TestDataCollection();
        $data = [
            ['id' => $ids->getBytes('r')],
            ['id' => $ids->getBytes('a'), 'parent_id' => $ids->getBytes('r')],
            ['id' => $ids->getBytes('aa'), 'parent_id' => $ids->getBytes('a')],
            ['id' => $ids->getBytes('ab'), 'parent_id' => $ids->getBytes('a')],
            ['id' => $ids->getBytes('b'), 'parent_id' => $ids->getBytes('r')],
        ];
        foreach ($data as $row) {
            $row['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $row['version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

            if (isset($row['parent_id'])) {
                $row['parent_version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
            }

            $connection->insert('test_tree', $row);
        }

        $definition = new TestTreeDefinition();
        $this->getContainer()
            ->get(DefinitionInstanceRegistry::class)
            ->register($definition);

        $treeUpdater = new TreeUpdater(
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get(Connection::class)
        );

        $context = Context::createDefaultContext();

        $treeUpdater->batchUpdate($ids->getList(['r', 'a', 'b', 'aa', 'ab']), 'test_tree', $context);

        $r = $this->fetch($ids->getBytes('r'), 'test_tree');
        static::assertSame('1', $r['test_level']);
        static::assertNull($r['test_path']);

        $a = $this->fetch($ids->getBytes('a'), 'test_tree');
        static::assertSame('2', $a['test_level']);
        static::assertSame('|' . $ids->get('r') . '|', $a['test_path']);

        $b = $this->fetch($ids->getBytes('b'), 'test_tree');
        static::assertSame('2', $b['test_level']);
        static::assertSame('|' . $ids->get('r') . '|', $b['test_path']);

        $aa = $this->fetch($ids->getBytes('aa'), 'test_tree');
        static::assertSame('3', $aa['test_level']);
        static::assertSame('|' . $ids->get('r') . '|' . $ids->get('a') . '|', $aa['test_path']);

        $ab = $this->fetch($ids->getBytes('ab'), 'test_tree');
        static::assertSame('3', $ab['test_level']);
        static::assertSame('|' . $ids->get('r') . '|' . $ids->get('a') . '|', $ab['test_path']);
    }

    public function testTreeUpdateWithoutVersion(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $ids = new TestDataCollection();
        $data = [
            ['id' => $ids->getBytes('r')],
            ['id' => $ids->getBytes('a'), 'parent_id' => $ids->getBytes('r')],
            ['id' => $ids->getBytes('aa'), 'parent_id' => $ids->getBytes('a')],
            ['id' => $ids->getBytes('ab'), 'parent_id' => $ids->getBytes('a')],
            ['id' => $ids->getBytes('b'), 'parent_id' => $ids->getBytes('r')],
        ];
        foreach ($data as $row) {
            $row['created_at'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            if (isset($row['parent_id'])) {
                $row['parent_version_id'] = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
            }

            $connection->insert('test_tree_without_version', $row);
        }

        $definition = new TestTreeDefinitionWithoutVersion();
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);
        $definition->compile($registry);

        $treeUpdater = new TreeUpdater($registry, $this->getContainer()->get(Connection::class));
        $context = Context::createDefaultContext();

        $treeUpdater->batchUpdate($ids->getList(['r', 'a', 'b', 'aa', 'ab']), 'test_tree_without_version', $context);

        $r = $this->fetch($ids->getBytes('r'), 'test_tree_without_version');
        static::assertSame('1', $r['test_level']);
        static::assertNull($r['test_path']);

        $a = $this->fetch($ids->getBytes('a'), 'test_tree_without_version');
        static::assertSame('2', $a['test_level']);
        static::assertSame('|' . $ids->get('r') . '|', $a['test_path']);

        $b = $this->fetch($ids->getBytes('b'), 'test_tree_without_version');
        static::assertSame('2', $b['test_level']);
        static::assertSame('|' . $ids->get('r') . '|', $b['test_path']);

        $aa = $this->fetch($ids->getBytes('aa'), 'test_tree_without_version');
        static::assertSame('3', $aa['test_level']);
        static::assertSame('|' . $ids->get('r') . '|' . $ids->get('a') . '|', $aa['test_path']);

        $ab = $this->fetch($ids->getBytes('ab'), 'test_tree_without_version');
        static::assertSame('3', $ab['test_level']);
        static::assertSame('|' . $ids->get('r') . '|' . $ids->get('a') . '|', $ab['test_path']);
    }

    private function fetch(string $id, string $table): array
    {
        return $this->getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT test_level, test_path FROM ' . $table . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}

/**
 * @internal
 */
class TestTreeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'test_tree';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.3.3.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new VersionField(),

            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new TreeLevelField('test_level', 'testLevel'))->addFlags(new ApiAware()),
            (new TreePathField('test_path', 'testPath'))->addFlags(new ApiAware()),
        ]);
    }
}

/**
 * @internal
 */
class TestTreeDefinitionWithoutVersion extends EntityDefinition
{
    final public const ENTITY_NAME = 'test_tree_without_version';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.8.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),

            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new TreeLevelField('test_level', 'testLevel'))->addFlags(new ApiAware()),
            (new TreePathField('test_path', 'testPath'))->addFlags(new ApiAware()),
        ]);
    }
}
