<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TranslatedVersionsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var string[]
     */
    private array $languages = [
        'en-GB', 'de-DE',
    ];

    public function testTranslationsAreAllSelectable(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');

        $this->createManufacturer($manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $deContext = $this->createDeContext($enContext);
        $deVersionContext = $deContext->createWithVersionId($versionId);

        $manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-de-DE',
        ]], $deVersionContext);

        $enOriginal = $manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->first();
        $enVersion = $manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->first();
        $deOriginal = $manufacturerRepository->search(new Criteria([$manufacturerId]), $deContext)->first();
        $deVersion = $manufacturerRepository->search(new Criteria([$manufacturerId]), $deVersionContext)->first();

        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-de-DE', $deOriginal->getName());
        static::assertSame('version-de-DE', $deVersion->getName());
    }

    public function testTranslationsFallbackToOriginal(): void
    {
        $enContext = Context::createDefaultContext();
        $manufacturerId = Uuid::randomHex();
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');

        $this->createManufacturer($manufacturerRepository, $manufacturerId, $enContext);

        $versionId = $manufacturerRepository->createVersion($manufacturerId, $enContext);
        $enVersionContext = $enContext->createWithVersionId($versionId);
        $deContext = $this->createDeContext($enContext);
        $deVersionContext = $deContext->createWithVersionId($versionId);

        $manufacturerRepository->update([[
            'id' => $manufacturerId,
            'name' => 'version-en-GB',
        ]], $enVersionContext);

        $enOriginal = $manufacturerRepository->search(new Criteria([$manufacturerId]), $enContext)->first();
        $enVersion = $manufacturerRepository->search(new Criteria([$manufacturerId]), $enVersionContext)->first();
        $deOriginal = $manufacturerRepository->search(new Criteria([$manufacturerId]), $deContext)->first();
        $deVersion = $manufacturerRepository->search(new Criteria([$manufacturerId]), $deVersionContext)->first();

        static::assertSame('original-en-GB', $enOriginal->getName());
        static::assertSame('version-en-GB', $enVersion->getName());
        static::assertSame('original-de-DE', $deOriginal->getName());
        static::assertSame('original-de-DE', $deVersion->getName());
    }

    public function testInheritenceWithProductsAllAreTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $productRepository = $this->getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct();

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $deContext));
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-de-DE']], $deVersionContext);

        $this->assertProductNames([
            ['child-original-en-GB', $enContext],
            ['child-original-de-DE', $deContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-version-de-DE', $deVersionContext],
        ], $ids->get('child'));
        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithProductsOnlyEnInVersionTranslated(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $productRepository = $this->getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct();

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $deContext));
        $productRepository->update([['id' => $ids->get('child'), 'name' => 'child-version-en-GB']], $enVersionContext);

        $this->assertProductNames([
            ['child-original-en-GB', $enContext],
            ['child-original-de-DE', $deContext],
            ['child-version-en-GB', $enVersionContext],
            ['child-original-de-DE', $deVersionContext],
        ], $ids->get('child'));
        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));
    }

    public function testInheritanceWithOnlyParentTranslations(): void
    {
        $enContext = Context::createDefaultContext();
        $deContext = $this->createDeContext($enContext);
        $productRepository = $this->getContainer()->get('product.repository');

        $ids = $this->createParentChildProduct(false);

        $enVersionContext = $enContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $enContext));
        $deVersionContext = $deContext->createWithVersionId($productRepository->createVersion($ids->get('child'), $deContext));

        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('parent'));

        $this->assertProductNames([
            ['parent-original-en-GB', $enContext],
            ['parent-original-de-DE', $deContext],
            ['parent-original-en-GB', $enVersionContext],
            ['parent-original-de-DE', $deVersionContext],
        ], $ids->get('child'));
    }

    public function testFieldResolverThrowsOnNotTranslatedEntities(): void
    {
        $resolver = $this->getContainer()->get(TranslationFieldResolver::class);
        $context = new FieldResolverContext(
            '',
            '',
            new TranslatedField(''),
            $this->getContainer()->get(ProductManufacturerTranslationDefinition::class),
            $this->getContainer()->get(ProductManufacturerTranslationDefinition::class),
            new QueryBuilder($this->getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        );

        $this->expectException(\RuntimeException::class);
        $resolver->join($context);
    }

    public function testFieldResolverReturnsOnNotTranslatedFields(): void
    {
        $resolver = $this->getContainer()->get(TranslationFieldResolver::class);
        $result = $resolver->join(new FieldResolverContext(
            '',
            'THIS_SHOULD_BE_RETURNED',
            new StringField('', ''),
            $this->getContainer()->get(ProductManufacturerDefinition::class),
            $this->getContainer()->get(ProductManufacturerDefinition::class),
            new QueryBuilder($this->getContainer()->get(Connection::class)),
            Context::createDefaultContext(),
            null
        ));

        static::assertSame('THIS_SHOULD_BE_RETURNED', $result);
    }

    private function createManufacturer(EntityRepository $productManufacturerRepository, string $productManufacturerId, Context $context): void
    {
        $translations = $this->getTestTranslations();
        $productManufacturerRepository->create([[
            'id' => $productManufacturerId,
            'translations' => $translations,
        ]], $context);
    }

    private function createDeContext(Context $enContext): Context
    {
        $deLanguageId = $this->getDeDeLanguageId();

        return new Context(
            $enContext->getSource(),
            $enContext->getRuleIds(),
            $enContext->getCurrencyId(),
            [$deLanguageId, $enContext->getLanguageId()],
            $enContext->getVersionId(),
            $enContext->getCurrencyFactor(),
            $enContext->considerInheritance(),
            $enContext->getTaxState()
        );
    }

    private function getTestTranslations(string $prefix = ''): array
    {
        $translations = [];
        foreach ($this->languages as $locale) {
            $translations[$locale] = ['name' => $prefix . 'original-' . $locale];
        }

        return $translations;
    }

    private function assertProductNames(array $assertions, string $id): void
    {
        foreach ($assertions as [$name, $context]) {
            $this->assertProductName($name, $id, $context);
        }
    }

    private function assertProductName(string $name, string $id, Context $context): void
    {
        $context->setConsiderInheritance(true);

        /** @var ProductEntity $product */
        $product = $this->getContainer()
            ->get('product.repository')
            ->search(new Criteria([$id]), $context)->first();

        static::assertTrue($context->considerInheritance());
        static::assertSame($name, $product->getTranslated()['name'], sprintf(
            'Expected %s with language chain %s but got %s, version context: %s',
            $name,
            (string) print_r($context->getLanguageIdChain(), true),
            $product->getName(),
            $context->getVersionId() === Defaults::LIVE_VERSION ? 'NO' : 'YES'
        ));

        $context->setConsiderInheritance(false);
    }

    private function createParentChildProduct(bool $addChildTranslations = true): IdsCollection
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $parentProduct = (new ProductBuilder($ids, 'parent'))
            ->price(100)
            ->build();

        $childProduct = (new ProductBuilder($ids, 'child'))
            ->parent('parent')
            ->price(100)
            ->build();

        unset($childProduct['name'], $parentProduct['name']);

        $parentProduct['translations'] = $this->getTestTranslations('parent-');

        if ($addChildTranslations) {
            $childProduct['translations'] = $this->getTestTranslations('child-');
        }

        $this->getContainer()
            ->get('product.repository')
            ->create([$parentProduct, $childProduct], $context);

        return $ids;
    }
}
