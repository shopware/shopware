<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\ThumbnailReadProjection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThumbnailReadProjection::class)]
class ThumbnailReadProjectionTest extends TestCase
{
    public function testNestedProjectionUsesMediaAlias(): void
    {
        $query = new QueryBuilder(static::createStub(Connection::class));
        (new ThumbnailReadProjection())->project(null, new MediaDefinition(), new JsonField('thumbnails_ro', 'thumbnailsRo'), 'product.cover.media', $query);

        $select = array_values($query->getSelectParts());
        static::assertCount(1, $select);
        static::assertStringContainsString('t.media_id = `product.cover.media`.id', $select[0]);
        static::assertStringEndsWith('AS `product.cover.media.thumbnailsRo`', $select[0]);
    }

    public function testExplicitAssociationLeavesTheQueryUntouched(): void
    {
        $query = new QueryBuilder(static::createStub(Connection::class));
        $query->addSelect('media.id');
        $before = $query->getSelectParts();
        $criteria = (new Criteria())->addAssociation('thumbnails');

        (new ThumbnailReadProjection())->project($criteria, new MediaDefinition(), new JsonField('thumbnails_ro', 'thumbnailsRo'), 'media', $query);

        static::assertSame($before, $query->getSelectParts());
    }
}
