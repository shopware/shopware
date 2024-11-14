<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EntityReaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityReader $entityReader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityReader = new EntityReader(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(EntityHydrator::class),
            $this->getContainer()->get(EntityDefinitionQueryHelper::class),
            $this->getContainer()->get(SqlQueryParser::class),
            $this->getContainer()->get(CriteriaQueryBuilder::class),
            $this->getContainer()->get('logger'),
            $this->getContainer()->get(CriteriaFieldsResolver::class)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testReadLoadsTranslationsAssociations(): void
    {
        $productId = $this->createProduct(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: null,
        );

        $criteria = new Criteria();
        $criteria->addAssociations(['translations']);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        static::assertInstanceOf(ProductCollection::class, $products);
        $translations = $products->get($productId)?->getTranslations();
        static::assertNotNull($translations);
        static::assertCount(2, $translations);
        $deDeTranslation = $translations->filterByLanguageId($this->getDeDeLanguageId())->first();
        static::assertEquals('Deutscher Name', $deDeTranslation?->get('name'));
    }

    public function testReadLoadsTranslationsAssociationsWithCriteriaFields(): void
    {
        $productId = $this->createProduct(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: null,
        );

        $criteria = new Criteria();
        $criteria->addAssociations(['translations']);
        $criteria->addFields(['translations.name']);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        $translations = $products->get($productId)?->get('translations');
        static::assertInstanceOf(EntityCollection::class, $translations);
        static::assertCount(2, $translations);
        $deDeTranslation = $translations
            ->filter(fn (Entity $entity) => $entity->get('languageId') === $this->getDeDeLanguageId())
            ->first();
        static::assertEquals('Deutscher Name', $deDeTranslation?->get('name'));
    }

    public function testReadLoadsTranslatedFieldsInCorrectLanguage(): void
    {
        $productId = $this->createProduct(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Default Name',
        );

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            new Criteria(),
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        static::assertInstanceOf(ProductCollection::class, $products);
        $translatedFields = $products->get($productId)?->get('translated');
        static::assertEquals('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsInCorrectLanguageWithCriteriaFields(): void
    {
        $productId = $this->createProduct(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Default name',
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        $translatedFields = $products->get($productId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertEquals('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingLanguageOverrides(): void
    {
        $productId = $this->createProduct(
            deDeTranslation: null,
            defaultTranslation: 'Fallback name',
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            self::createLocalizedContext([
                $this->getDeDeLanguageId(),
                Defaults::LANGUAGE_SYSTEM,
            ]),
        );

        $translatedFields = $products->get($productId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertEquals('Fallback name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingInheritanceAndLanguageOverridesPreferringOwnTranslation(): void
    {
        $ids = new IdsCollection();
        $this->createProduct(
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            productNumber: 'parent-product',
            ids: $ids,
        );
        $productId = $this->createProduct(
            deDeTranslation: 'Deutscher Name',
            defaultTranslation: 'Fallback name',
            parentProductNumber: 'parent-product',
            ids: $ids,
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $context = self::createLocalizedContext([
            $this->getDeDeLanguageId(),
            Defaults::LANGUAGE_SYSTEM,
        ]);
        $context->setConsiderInheritance(true);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            $context,
        );

        $translatedFields = $products->get($productId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertEquals('Deutscher Name', $translatedFields['name']);
    }

    public function testReadLoadsTranslatedFieldsByApplyingInheritanceAndLanguageOverridesUsingParentTranslationAsFallback(): void
    {
        $ids = new IdsCollection();
        $this->createProduct(
            deDeTranslation: 'Parent: Deutscher Name',
            defaultTranslation: 'Parent: Fallback name',
            productNumber: 'parent-product',
            ids: $ids,
        );
        $productId = $this->createProduct(
            deDeTranslation: null,
            defaultTranslation: 'Fallback name',
            parentProductNumber: 'parent-product',
            ids: $ids,
        );

        $criteria = new Criteria();
        // Selecting the name field is necessary to include the the translated value in the result
        $criteria->addFields(['name']);

        $context = self::createLocalizedContext([
            $this->getDeDeLanguageId(),
            Defaults::LANGUAGE_SYSTEM,
        ]);
        $context->setConsiderInheritance(true);

        $products = $this->entityReader->read(
            $this->getContainer()->get(ProductDefinition::class),
            $criteria,
            $context,
        );

        $translatedFields = $products->get($productId)?->get('translated');
        static::assertNotNull($translatedFields);
        static::assertCount(1, $translatedFields);
        static::assertEquals('Parent: Deutscher Name', $translatedFields['name']);
    }

    public function testAssociationWithOrderBy(): void
    {
        $ids = new IdsCollection();
        $productBuilder = (new ProductBuilder($ids, 'test'))
            ->active(true)
            ->price(100)
            ->visibility()
            ->variant(
                (new ProductBuilder($ids, 'test-1'))
                    ->active(true)
                    ->name('foo')
                    ->visibility()
                    ->build()
            );

        /** @var EntityRepository<ProductCollection> $entityRepository */
        $entityRepository = $this->getContainer()->get('product.repository');
        $entityRepository->create(
            [$productBuilder->build()],
            Context::createDefaultContext()
        );

        $criteria = new Criteria();

        $criteria->getAssociation('children')
            ->addSorting(new FieldSorting('purchaseUnit'))
            ->addGroupField(new FieldGrouping('displayGroup'));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $result = $entityRepository->search($criteria, $context);

        static::assertEquals($ids->get('test'), $result->getEntities()->first()?->getId());
    }

    private function createProduct(
        ?string $deDeTranslation,
        ?string $defaultTranslation,
        string $productNumber = 'product-1',
        ?string $parentProductNumber = null,
        ?IdsCollection $ids = null
    ): string {
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

        $this->getContainer()->get('product.repository')->create(
            [$productBuilder->build()],
            Context::createDefaultContext()
        );

        return $ids->get($productNumber);
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
