<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\AbstractFieldQueryBuilder;
use Shopware\Elasticsearch\NestedFieldQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\ResolvedField;

/**
 * @internal
 */
#[CoversClass(NestedFieldQueryBuilder::class)]
#[Package('inventory')]
class NestedFieldQueryBuilderTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $builder = new NestedFieldQueryBuilder($inner);

        static::assertSame($inner, $builder->getDecorated());
    }

    public function testWrapsInNestedQueryWhenRootIsSet(): void
    {
        $termQuery = new TermQuery('tags.name', 'foo');

        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn($termQuery);

        $builder = new NestedFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'), 'tags');
        $config = new SearchFieldConfig('tags.name', 500, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(NestedQuery::class, $query);
        $array = $query->toArray();
        static::assertSame('tags', $array['nested']['path']);
        static::assertSame($termQuery->toArray(), $array['nested']['query']);
    }

    public function testReturnsQueryAsIsWhenRootIsNull(): void
    {
        $termQuery = new TermQuery('name', 'foo');

        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn($termQuery);

        $builder = new NestedFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertSame($termQuery, $query);
    }

    public function testReturnsNullWhenInnerReturnsNull(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn(null);

        $builder = new NestedFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'), 'tags');
        $config = new SearchFieldConfig('tags.name', 500, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNull($query);
    }

    public function testReturnsNullWhenInnerReturnsNullAndNoRoot(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn(null);

        $builder = new NestedFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNull($query);
    }
}
