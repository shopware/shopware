<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class AclCriteriaValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    private AclCriteriaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = static::getContainer()->get(AclCriteriaValidator::class);
    }

    /**
     * @param array<int, string> $privileges
     */
    #[DataProvider('criteriaProvider')]
    public function testValidateCriteria(array $privileges, Criteria $criteria, bool $pass): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($privileges);

        $context = new Context(
            $source,
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM]
        );

        $missing = $this->validator->validate(ProductDefinition::ENTITY_NAME, $criteria, $context);

        if ($pass) {
            static::assertEmpty($missing);

            return;
        }

        static::assertNotEmpty($missing);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|bool|Criteria>>
     */
    public static function criteriaProvider(): iterable
    {
        yield 'Has read permission for root entity' => [
            ['product:read'],
            new Criteria(),
            true,
        ];
        yield 'Missing permissions for root entity' => [
            [],
            new Criteria(),
            false,
        ];
        yield 'Has permissions for association' => [
            ['product:read', 'product_manufacturer:read'],
            (new Criteria())->addAssociation('manufacturer'),
            true,
        ];
        yield 'Missing permissions for association' => [
            ['product:read'],
            (new Criteria())->addAssociation('manufacturer'),
            false,
        ];
        yield 'Has permissions for association but not for root' => [
            ['product_manufacturer:read'],
            (new Criteria())->addAssociation('manufacturer'),
            false,
        ];
        yield 'Has permissions for nested association' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())->addAssociation('categories.media'),
            true,
        ];
        yield 'Missing permissions for nested association' => [
            ['product:read', 'category:read'],
            (new Criteria())->addAssociation('categories.media'),
            false,
        ];
        yield 'Has permissions for filter' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.active', true)),
            true,
        ];
        yield 'Missing permissions for filter' => [
            ['product:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.active', true)),
            false,
        ];
        yield 'Has permissions for nested filter' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.media.private', true)),
            true,
        ];
        yield 'Missing permissions for nested filter' => [
            ['product:read'],
            (new Criteria())
                ->addFilter(new EqualsFilter('categories.media.private', true)),
            false,
        ];
        yield 'Has permissions for post filter' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.active', true)),
            true,
        ];
        yield 'Missing permissions for post filter' => [
            ['product:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.active', true)),
            false,
        ];
        yield 'Has permissions for nested post filter' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.media.private', true)),
            true,
        ];
        yield 'Missing permissions for nested post filter' => [
            ['product:read'],
            (new Criteria())
                ->addPostFilter(new EqualsFilter('categories.media.private', true)),
            false,
        ];
        yield 'Has permissions for sorting' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.active')),
            true,
        ];
        yield 'Missing permissions for sorting' => [
            ['product:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.active')),
            false,
        ];
        yield 'Has permissions for nested sorting' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested sorting' => [
            ['product:read'],
            (new Criteria())
                ->addSorting(new FieldSorting('categories.media.private')),
            false,
        ];
        yield 'Has permissions for query' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.active', true), 100)),
            true,
        ];
        yield 'Missing permissions for query' => [
            ['product:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.active', true), 100)),
            false,
        ];
        yield 'Has permissions for nested query' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.media.private', true), 100)),
            true,
        ];
        yield 'Missing permissions for nested query' => [
            ['product:read'],
            (new Criteria())
                ->addQuery(new ScoreQuery(new EqualsFilter('categories.media.private', true), 100)),
            false,
        ];
        yield 'Has permissions for grouping' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.active')),
            true,
        ];
        yield 'Missing permissions for grouping' => [
            ['product:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.active')),
            false,
        ];
        yield 'Has permissions for nested grouping' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested grouping' => [
            ['product:read'],
            (new Criteria())
                ->addGroupField(new FieldGrouping('categories.media.private')),
            false,
        ];
        yield 'Has permissions for aggregation' => [
            ['product:read', 'category:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.active')),
            true,
        ];
        yield 'Missing permissions for aggregation' => [
            ['product:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.active')),
            false,
        ];
        yield 'Has permissions for nested aggregation' => [
            ['product:read', 'category:read', 'media:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.media.private')),
            true,
        ];
        yield 'Missing permissions for nested aggregation' => [
            ['product:read'],
            (new Criteria())
                ->addAggregation(new CountAggregation('count-agg', 'categories.media.private')),
            false,
        ];
    }
}
