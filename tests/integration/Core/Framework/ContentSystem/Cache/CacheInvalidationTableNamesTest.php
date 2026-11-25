<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * {@see CacheInvalidationSubscriber} queries each assignment table by name; a name no table answers to
 * makes invalidation a silent no-op, so every name it can reach is checked against the live schema.
 *
 * @internal
 */
#[Package('framework')]
class CacheInvalidationTableNamesTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('every table the cache invalidation subscriber names exists in the schema')]
    public function testEveryInvalidatedTableExists(): void
    {
        $sectionAssignments = static::getContainer()->getParameter('shopware.content_system.section_assignment_entities');
        static::assertIsArray($sectionAssignments);

        $tables = [
            ContentLayoutDefinition::ENTITY_NAME,
            ProductContentLayoutDefinition::ENTITY_NAME,
            CategoryContentLayoutDefinition::ENTITY_NAME,
            LandingPageContentLayoutDefinition::ENTITY_NAME,
            ...array_keys($sectionAssignments),
        ];

        $schemaManager = static::getContainer()->get(Connection::class)->createSchemaManager();

        foreach ($tables as $table) {
            static::assertIsString($table);
            static::assertTrue($schemaManager->tablesExist([$table]), \sprintf('Table "%s" does not exist.', $table));
        }
    }
}
