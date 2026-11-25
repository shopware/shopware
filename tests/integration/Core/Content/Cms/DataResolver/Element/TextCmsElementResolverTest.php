<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the sanitizer on both paths a product description takes: the DAL write and the CMS text slot render.
 *
 * @internal
 */
#[Package('discovery')]
class TextCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('Stores and renders a product description, keeping escaped and double-encoded HTML as text')]
    #[DataProvider('descriptionProvider')]
    public function testWritesAndRendersProductDescription(string $input, string $stored, string $rendered): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->assertWriteAndRender($input, $stored, $rendered);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed together with the repair of double-encoded entities
     */
    #[TestDox('Without 6.8 behaviour, repairs double-encoded entities on write and render but never into markup')]
    #[DataProvider('descriptionProviderBefore68')]
    public function testWritesAndRendersProductDescriptionWithoutMajor(string $input, string $stored, string $rendered): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->assertWriteAndRender($input, $stored, $rendered);
    }

    /**
     * Input, stored description, rendered CMS text
     */
    public static function descriptionProvider(): \Generator
    {
        yield 'single encoded umlaut and markup' => [
            '<p>Luxuri&ouml;ser <b>Mantel</b></p>',
            '<p>Luxuriöser <b>Mantel</b></p>',
            '<p>Luxuriöser <b>Mantel</b></p>',
        ];

        yield 'escaped script stays text' => [
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        ];

        yield 'double encoded umlaut stays text' => [
            'Luxuri&amp;ouml;ser Mantel',
            'Luxuri&amp;ouml;ser Mantel',
            'Luxuri&amp;ouml;ser Mantel',
        ];

        yield 'double encoded script stays text' => [
            '&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;',
            '&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;',
            '&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;',
        ];

        yield 'numerically encoded ampersand stays text' => [
            '&#38;lt;script&#38;gt;',
            '&amp;lt;script&amp;gt;',
            '&amp;lt;script&amp;gt;',
        ];
    }

    /**
     * Input, stored description, rendered CMS text
     *
     * @deprecated tag:v6.8.0 - Will be removed together with the repair of double-encoded entities
     */
    public static function descriptionProviderBefore68(): \Generator
    {
        yield 'single encoded umlaut and markup' => [
            '<p>Luxuri&ouml;ser <b>Mantel</b></p>',
            '<p>Luxuriöser <b>Mantel</b></p>',
            '<p>Luxuriöser <b>Mantel</b></p>',
        ];

        yield 'escaped script stays text' => [
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        ];

        yield 'double encoded umlaut is repaired' => [
            'Luxuri&amp;ouml;ser Mantel',
            'Luxuriöser Mantel',
            'Luxuriöser Mantel',
        ];

        yield 'double encoded script is repaired to escaped text, never to markup' => [
            '&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            '&lt;script&gt;alert(1)&lt;/script&gt;',
        ];

        yield 'numerically encoded ampersand loses one layer on render but stays escaped' => [
            '&#38;lt;script&#38;gt;',
            '&amp;lt;script&amp;gt;',
            '&lt;script&gt;',
        ];
    }

    private function assertWriteAndRender(string $input, string $stored, string $rendered): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create([
            (new ProductBuilder($ids, 'product'))
                ->price(100)
                ->description($input)
                ->build(),
        ], $context);

        $product = $productRepository->search(new Criteria([$ids->get('product')]), $context)->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($stored, $product->getDescription());

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $resolverContext = new EntityResolverContext(
            $salesChannelContext,
            new Request(),
            static::getContainer()->get(ProductDefinition::class),
            $product
        );

        $fieldConfigs = new FieldConfigCollection();
        $fieldConfigs->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = new CmsSlotEntity();
        $slot->setId(Uuid::randomHex());
        $slot->setType('text');
        $slot->setSlot('content');
        $slot->setBlockId(Uuid::randomHex());
        $slot->setFieldConfig($fieldConfigs);

        $slots = static::getContainer()->get(CmsSlotsDataResolver::class)
            ->resolve(new CmsSlotCollection([$slot]), $resolverContext);

        $text = $slots->first()?->getData();
        static::assertInstanceOf(TextStruct::class, $text);
        static::assertSame($rendered, $text->getContent());
    }
}
