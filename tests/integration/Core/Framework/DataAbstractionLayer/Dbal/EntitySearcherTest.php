<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Category\CategoryBuilder;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class EntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntitySearcher $entitySearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entitySearcher = new EntitySearcher(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(EntityDefinitionQueryHelper::class),
            $this->getContainer()->get(CriteriaQueryBuilder::class),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testSearchFiltersByTranslatedFieldsInAssociations(): void
    {
        $ids = new IdsCollection();
        $this->createCategory(
            defaultTranslation: 'Category 1',
            deDeTranslation: null,
            ids: $ids,
        );
        $this->createCategory(
            defaultTranslation: 'Category 2',
            deDeTranslation: 'Kategorie 2',
            ids: $ids,
        );
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
            categories: ['Category 1'],
            ids: $ids,
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
            categories: ['Category 2'],
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Deutscher Name'));
        $criteria->addFilter(new EqualsFilter('categories.name', 'Category 1'));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchFiltersByTranslatedFieldsInAssociationsByApplyingLanguageOverrides(): void
    {
        $ids = new IdsCollection();
        $this->createCategory(
            defaultTranslation: 'category-1',
            deDeTranslation: 'Kategorie 1',
            ids: $ids,
        );
        $this->createCategory(
            defaultTranslation: 'category-2',
            deDeTranslation: 'Kategorie 2',
            ids: $ids,
        );
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: 'Deutscher Name',
            categories: ['category-1'],
            ids: $ids,
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            categories: ['category-2'],
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'Deutscher Name'));
        $criteria->addFilter(new EqualsFilter('categories.name', 'Kategorie 1'));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchFiltersByTranslatedFieldsByApplyingInheritanceAndLanguageOverridesPreferringOwnTranslation(): void
    {
        $ids = new IdsCollection();
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            ids: $ids,
        );
        $productId2 = $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutscher Name',
            parentProductNumber: 'product-1',
            ids: $ids,
        );
        // The following product should not be matched because its deDeTranslation takes precedence over the parent's
        $this->createProduct(
            productNumber: 'product-3',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
            parentProductNumber: 'product-1',
            ids: $ids,
        );

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('name', 'Deutscher Name'));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals(
            [
                $productId1,
                $productId2,
            ],
            $productIds->getIds(),
        );
    }

    public function testSearchAppliesTermToTranslatedFields(): void
    {
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: null,
            defaultTranslation: 'German name',
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
        );

        $criteria = new Criteria();
        $criteria->setTerm('German name');

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchAppliesTermToTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
        );

        $criteria = new Criteria();
        $criteria->setTerm('Deutscher Name');

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchAppliesQueryToTranslatedFields(): void
    {
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: null,
            defaultTranslation: 'German name',
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
        );

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('name', 'German name'), score: 100));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchAppliesQueryToTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $productId1 = $this->createProduct(
            productNumber: 'product-1',
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'German name',
        );
        $this->createProduct(
            productNumber: 'product-2',
            deDeTranslation: 'Deutsches Produkt',
            defaultTranslation: 'German product',
        );

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('name', 'Deutscher Name'), score: 100));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    public function testSearchFiltersByAndAppliesQueryToTranslatedFields(): void
    {
        $ids = new IdsCollection();
        $productBuilder1 = $this->buildProduct(
            deDeTranslation: 'Deutscher Name',
            productNumber: 'product-1',
            ids: $ids,
        );
        $productBuilder1->translation($this->getDeDeLanguageId(), 'keywords', 'Schlagwort');
        $productBuilder2 = $this->buildProduct(
            deDeTranslation: 'Deutsches Produkt',
            productNumber: 'product-2',
            ids: $ids,
        );
        $this->getContainer()->get('product.repository')->create(
            [
                $productBuilder1->build(),
                $productBuilder2->build(),
            ],
            Context::createDefaultContext(),
        );
        $productId1 = $ids->get('product-1');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('keywords', 'Schlagwort'));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Deutsch'), score: 100));

        $productIds = $this->entitySearcher->search(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertEquals([$productId1], $productIds->getIds());
    }

    /**
     * @param list<string> $categories
     */
    private function createProduct(
        string $productNumber,
        ?string $deDeTranslation,
        ?string $defaultTranslation = null,
        array $categories = [],
        ?string $parentProductNumber = null,
        ?IdsCollection $ids = null,
    ): string {
        $ids ??= new IdsCollection();
        $productBuilder = $this->buildProduct(
            deDeTranslation: $deDeTranslation,
            defaultTranslation: $defaultTranslation,
            productNumber: $productNumber,
            parentProductNumber: $parentProductNumber,
            ids: $ids,
        );
        $productBuilder->categories($categories);

        $this->getContainer()->get('product.repository')->create(
            [$productBuilder->build()],
            Context::createDefaultContext()
        );

        return $ids->get($productNumber);
    }

    private function buildProduct(
        ?string $deDeTranslation = null,
        ?string $defaultTranslation = null,
        string $productNumber = 'product-1',
        ?string $parentProductNumber = null,
        ?IdsCollection $ids = null,
    ): ProductBuilder {
        $ids ??= new IdsCollection();
        $productBuilder = new ProductBuilder($ids, $productNumber);
        $productBuilder->price(100);
        if ($deDeTranslation !== null) {
            $productBuilder->translation($this->getDeDeLanguageId(), 'name', $deDeTranslation);
        }
        if ($defaultTranslation !== null) {
            $productBuilder->translation(Defaults::LANGUAGE_SYSTEM, 'name', $defaultTranslation);
        }
        if ($parentProductNumber !== null) {
            $productBuilder->parent($parentProductNumber);
        }

        return $productBuilder;
    }

    private function createCategory(
        string $defaultTranslation,
        ?string $deDeTranslation,
        ?IdsCollection $ids = null
    ): string {
        $ids ??= new IdsCollection();
        // Category does not have a name filed but only translations, hence the default translation must be passed to
        // the builder as the name
        $categoryBuilder = new CategoryBuilder($ids, categoryName: $defaultTranslation);
        if ($deDeTranslation !== null) {
            $categoryBuilder->translation($this->getDeDeLanguageId(), 'name', $deDeTranslation);
        }

        $this->getContainer()->get('category.repository')->create(
            [$categoryBuilder->build()],
            Context::createDefaultContext(),
        );

        return $ids->get($defaultTranslation);
    }

    /**
     * @param list<string> $languageIdChain
     */
    private static function createLocalizedContext(array $languageIdChain): Context
    {
        return new Context(
            source: new SystemSource(),
            ruleIds: [],
            currencyId: Defaults::CURRENCY,
            languageIdChain: $languageIdChain,
        );
    }
}
