<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\PrimaryKeyScanQueryBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PrimaryKeyScanQueryBuilder::class)]
class PrimaryKeyScanQueryBuilderTest extends TestCase
{
    public function testJoinedQueryRetainsAliasAndOmitsIndexHint(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $query = new PrimaryKeyScanQueryBuilder($connection, 'media', 'idx_media_id_only');
        $query->select('`media`.id');

        static::assertStringContainsString('FROM `media` FORCE INDEX (`idx_media_id_only`)', $query->getSQL());

        $query->leftJoin('`media`', 'media_folder', 'folder', 'folder.id = `media`.media_folder_id');
        $sql = $query->getSQL();
        static::assertStringContainsString('LEFT JOIN media_folder folder', $sql);
        static::assertStringNotContainsString('FORCE INDEX', $sql);
    }
}
