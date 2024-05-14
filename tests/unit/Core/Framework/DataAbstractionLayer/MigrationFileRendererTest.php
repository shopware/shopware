<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;

/**
 * @internal
 */
#[CoversClass(MigrationFileRenderer::class)]
class MigrationFileRendererTest extends TestCase
{
    public function testRender(): void
    {
        $namespace = 'Test\Namespace';
        $className = 'Migration20231117120000TestEntity';
        $timestamp = '20231117120000';

        $queries = [
            'ALTER TABLE test_table ADD test_column INT DEFAULT NULL',
            'ALTER TABLE test_table DROP FOREIGN KEY fk_test',
        ];

        $migrationFileRenderer = new MigrationFileRenderer();
        $result = $migrationFileRenderer->render($namespace, $className, $timestamp, $queries);

        static::assertStringContainsString('namespace Test\Namespace;', $result);
        static::assertStringContainsString('class Migration20231117120000TestEntity extends MigrationStep', $result);
        static::assertStringContainsString('return 20231117120000;', $result);
        static::assertStringContainsString('public function update(Connection $connection): void', $result);
        static::assertStringContainsString($queries[0], $result);
        static::assertStringContainsString($queries[1], $result);
    }

    public function testCreateMigrationClassName(): void
    {
        $timestamp = '20231117120000';
        $entity = 'test_entity';

        $result = MigrationFileRenderer::createMigrationClassName($timestamp, $entity);

        static::assertEquals('Migration20231117120000TestEntity', $result);
    }
}
